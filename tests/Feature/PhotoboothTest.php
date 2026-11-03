<?php

namespace Tests\Feature;

use App\Models\PhotoSession;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoboothTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(array $overrides = []): Template
    {
        return Template::create(array_merge([
            'name'          => 'Uji Strip',
            'description'   => 'Template untuk pengujian',
            'category'      => 'strip',
            'tags'          => ['uji'],
            'slot_count'    => 2,
            'bg_color'      => '#131947',
            'is_active'     => true,
            'sort_order'    => 1,
            'layout_config' => [
                ['x' => 50, 'y' => 260, 'width' => 1100, 'height' => 620, 'photo' => 0],
                ['x' => 50, 'y' => 920, 'width' => 1100, 'height' => 620, 'photo' => 1],
            ],
        ], $overrides));
    }

    public function test_welcome_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('EXPO WISUDA');
    }

    public function test_gallery_lists_only_active_templates(): void
    {
        $this->makeTemplate(['name' => 'Template Aktif']);
        $this->makeTemplate(['name' => 'Template Mati', 'is_active' => false]);

        $this->get('/setup')->assertOk()->assertSee('Template Aktif')->assertDontSee('Template Mati');
    }

    public function test_inactive_template_cannot_start_a_session(): void
    {
        $t = $this->makeTemplate(['is_active' => false]);

        $this->get(route('photobooth.session', $t))->assertRedirect(route('photobooth.setup'));
    }

    public function test_camera_page_loads_for_active_template(): void
    {
        $t = $this->makeTemplate();

        $this->get(route('photobooth.session', $t))->assertOk()->assertSee('booth-config', false);
    }

    public function test_process_composes_and_stores_result(): void
    {
        Storage::fake('public');
        $t = $this->makeTemplate();

        $response = $this->postJson(route('photobooth.process', $t), [
            'photos' => [
                UploadedFile::fake()->image('a.jpg', 1280, 720),
                UploadedFile::fake()->image('b.jpg', 1280, 720),
            ],
        ]);

        $response->assertOk()->assertJsonStructure(['redirect']);
        $session = PhotoSession::firstOrFail();
        Storage::disk('public')->assertExists($session->final_image_path);
        $this->assertCount(2, $session->raw_photos);
        $this->get($response->json('redirect'))->assertOk();
    }

    public function test_process_rejects_wrong_photo_count(): void
    {
        Storage::fake('public');
        $t = $this->makeTemplate();

        $this->postJson(route('photobooth.process', $t), [
            'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
        ])->assertStatus(422)->assertJsonValidationErrors('photos');

        $this->assertDatabaseCount('photo_sessions', 0);
    }

    public function test_process_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $t = $this->makeTemplate(['slot_count' => 1, 'layout_config' => null]);

        $this->postJson(route('photobooth.process', $t), [
            'photos' => [UploadedFile::fake()->create('evil.php', 10, 'application/x-php')],
        ])->assertStatus(422);
    }

    public function test_unknown_result_token_returns_404(): void
    {
        $this->get('/result/tidak-ada')->assertNotFound();
    }

    public function test_media_route_blocks_path_traversal_and_unknown_folders(): void
    {
        $this->get('/media/..%2F.env')->assertNotFound();
        $this->get('/media/config/app.php')->assertNotFound();
        $this->get('/media/results/tidak-ada.jpg')->assertNotFound();
    }

    public function test_admin_routes_require_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/templates')->assertRedirect(route('login'));
        $this->get('/admin/sessions')->assertRedirect(route('login'));
    }

    public function test_admin_can_open_dashboard_and_manage_templates(): void
    {
        $user = User::factory()->create();
        $t = $this->makeTemplate();

        $this->actingAs($user)->get('/admin/dashboard')->assertOk();
        $this->actingAs($user)->get('/admin/templates')->assertOk()->assertSee('Uji Strip');
        $this->actingAs($user)->get(route('admin.templates.edit', $t))->assertOk();

        $this->actingAs($user)->patch(route('admin.templates.toggle', $t))->assertRedirect();
        $this->assertFalse($t->fresh()->is_active);
    }

    public function test_admin_cannot_save_template_with_invalid_layout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.templates.store'), [
            'name'          => 'Rusak',
            'category'      => 'strip',
            'slot_count'    => 2,
            'bg_color'      => '#131947',
            'layout_config' => '[{"x":0,"y":0,"width":5000,"height":10}]',
        ])->assertSessionHasErrors('layout_config');

        $this->assertDatabaseCount('templates', 0);
    }

    public function test_template_with_used_sessions_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $t = $this->makeTemplate();
        PhotoSession::create(['template_id' => $t->id, 'session_token' => 'abc', 'final_image_path' => 'results/abc_final.jpg', 'raw_photos' => []]);

        $this->actingAs($user)->delete(route('admin.templates.destroy', $t))->assertSessionHas('error');
        $this->assertDatabaseHas('templates', ['id' => $t->id]);
    }

    public function test_normalized_slots_handles_legacy_string_json(): void
    {
        $t = new Template(['slot_count' => 1]);
        $t->setRawAttributes(['slot_count' => 1, 'layout_config' => json_encode('[{"x":10,"y":20,"width":300,"height":200}]')]);

        $slots = $t->normalizedSlots();

        $this->assertSame(10, $slots[0]['x']);
        $this->assertSame(0, $slots[0]['photo']);
    }

    public function test_default_layout_is_used_when_config_is_empty(): void
    {
        $t = new Template(['slot_count' => 3, 'layout_config' => null]);

        $this->assertCount(3, $t->normalizedSlots());
    }

    public function test_result_uses_detected_lan_ip_when_accessed_on_localhost(): void
    {
        $t = $this->makeTemplate();
        $session = PhotoSession::create([
            'template_id'      => $t->id,
            'session_token'    => 'testlanip1234567890abcdef',
            'final_image_path' => 'results/test_final.jpg',
            'raw_photos'       => [],
        ]);

        $localIpFile = storage_path('local_ip.txt');
        $tunnelFile = storage_path('tunnel_url.txt');
        $backupTunnel = file_exists($tunnelFile) ? file_get_contents($tunnelFile) : null;
        $backupLocalIp = file_exists($localIpFile) ? file_get_contents($localIpFile) : null;
        @unlink($tunnelFile);
        file_put_contents($localIpFile, '172.27.19.40');

        try {
            $response = $this->get('/result/' . $session->session_token);
            $response->assertOk();
            $this->assertStringContainsString('172.27.19.40', $response->getContent());
        } finally {
            if ($backupLocalIp !== null) {
                file_put_contents($localIpFile, $backupLocalIp);
            } else {
                @unlink($localIpFile);
            }
            if ($backupTunnel !== null) {
                file_put_contents($tunnelFile, $backupTunnel);
            }
        }
    }

    public function test_result_prefers_tunnel_url_when_present(): void
    {
        $t = $this->makeTemplate();
        $session = PhotoSession::create([
            'template_id'      => $t->id,
            'session_token'    => 'testtunnel1234567890abcde',
            'final_image_path' => 'results/test_final.jpg',
            'raw_photos'       => [],
        ]);

        $tunnelFile = storage_path('tunnel_url.txt');
        $backupTunnel = file_exists($tunnelFile) ? file_get_contents($tunnelFile) : null;
        file_put_contents($tunnelFile, 'https://subdomain.trycloudflare.com');

        try {
            $response = $this->get('/result/' . $session->session_token);
            $response->assertOk();
            $this->assertStringContainsString('https://subdomain.trycloudflare.com', $response->getContent());
        } finally {
            if ($backupTunnel !== null) {
                file_put_contents($tunnelFile, $backupTunnel);
            } else {
                @unlink($tunnelFile);
            }
        }
    }
}
