<?php

namespace Tests\Feature;

use App\Models\PhotoSession;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleDriveTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_drive_status_reports_unconfigured_when_no_credentials(): void
    {
        Config::set('photobooth.google_drive.enabled', false);
        Config::set('photobooth.google_drive.client_id', '');
        Config::set('photobooth.google_drive.webhook_url', '');

        $response = $this->getJson('/api/google-drive/status');
        $response->assertStatus(503);
        $response->assertJson(['ok' => false, 'configured' => false]);
    }

    public function test_google_drive_status_reports_ok_with_webhook(): void
    {
        Config::set('photobooth.google_drive.enabled', true);
        Config::set('photobooth.google_drive.webhook_url', 'https://script.google.com/macros/s/test/exec');

        Http::fake([
            'https://script.google.com/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->getJson('/api/google-drive/status');
        $response->assertStatus(200);
        $response->assertJson(['ok' => true, 'configured' => true, 'method' => 'webhook']);
    }

    public function test_upload_session_to_google_drive_via_api(): void
    {
        Storage::fake('public');

        // Simpan file hasil dummy
        Storage::disk('public')->put('results/test_final.jpg', 'DUMMY_IMAGE_BINARY_DATA_123456');

        $template = Template::create([
            'name'          => 'Uji Strip',
            'category'      => 'strip',
            'slot_count'    => 2,
            'is_active'     => true,
            'layout_config' => [
                ['x' => 50, 'y' => 260, 'width' => 1100, 'height' => 620, 'photo' => 0],
                ['x' => 50, 'y' => 920, 'width' => 1100, 'height' => 620, 'photo' => 1],
            ],
        ]);
        $session = PhotoSession::create([
            'template_id'      => $template->id,
            'session_token'    => 'test_token_abc_12345678',
            'final_image_path' => 'results/test_final.jpg',
            'raw_photos'       => [],
        ]);

        Config::set('photobooth.google_drive.enabled', true);
        Config::set('photobooth.google_drive.webhook_url', 'https://script.google.com/macros/s/test/exec');

        Http::fake([
            'https://script.google.com/*' => Http::response([
                'ok'      => true,
                'fileId'  => 'gdrive_file_id_999',
                'url'     => 'https://drive.google.com/file/d/gdrive_file_id_999/view',
            ], 200),
        ]);

        $response = $this->postJson("/api/sessions/{$session->session_token}/upload-drive");
        $response->assertStatus(200);
        $response->assertJson([
            'ok'               => true,
            'google_drive_url' => 'https://drive.google.com/file/d/gdrive_file_id_999/view',
            'file_id'          => 'gdrive_file_id_999',
        ]);

        $session->refresh();
        $this->assertEquals('gdrive_file_id_999', $session->google_drive_file_id);
        $this->assertEquals('https://drive.google.com/file/d/gdrive_file_id_999/view', $session->google_drive_url);
        $this->assertEquals('synced', $session->google_drive_status);
        $this->assertNotNull($session->google_drive_synced_at);

        // Panggilan kedua tanpa force langsung mengembalikan status already_synced
        $repeat = $this->postJson("/api/sessions/{$session->session_token}/upload-drive");
        $repeat->assertStatus(200);
        $repeat->assertJson(['ok' => true, 'already_synced' => true]);
    }

    public function test_get_session_drive_status_api(): void
    {
        $template = Template::create([
            'name'          => 'Uji Strip',
            'category'      => 'strip',
            'slot_count'    => 2,
            'is_active'     => true,
            'layout_config' => [
                ['x' => 50, 'y' => 260, 'width' => 1100, 'height' => 620, 'photo' => 0],
                ['x' => 50, 'y' => 920, 'width' => 1100, 'height' => 620, 'photo' => 1],
            ],
        ]);
        $session = PhotoSession::create([
            'template_id'          => $template->id,
            'session_token'        => 'check_token_12345678',
            'final_image_path'     => 'results/final.jpg',
            'google_drive_file_id' => 'file_123',
            'google_drive_url'     => 'https://drive.google.com/file/d/file_123/view',
            'google_drive_status'  => 'synced',
            'google_drive_synced_at'=> now(),
        ]);

        $response = $this->getJson("/api/sessions/{$session->session_token}/drive");
        $response->assertStatus(200);
        $response->assertJson([
            'ok'               => true,
            'synced'           => true,
            'status'           => 'synced',
            'google_drive_url' => 'https://drive.google.com/file/d/file_123/view',
            'file_id'          => 'file_123',
        ]);
    }

    public function test_direct_google_drive_upload_api(): void
    {
        Config::set('photobooth.google_drive.enabled', true);
        Config::set('photobooth.google_drive.webhook_url', 'https://script.google.com/macros/s/test/exec');

        Http::fake([
            'https://script.google.com/*' => Http::response([
                'ok'     => true,
                'fileId' => 'direct_file_id_888',
                'url'    => 'https://drive.google.com/file/d/direct_file_id_888/view',
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('test_booth.jpg', 600, 800);

        $response = $this->postJson('/api/google-drive/upload', [
            'photo' => $file,
            'name'  => 'custom_booth_photo.jpg',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok'               => true,
            'google_drive_url' => 'https://drive.google.com/file/d/direct_file_id_888/view',
            'file_id'          => 'direct_file_id_888',
        ]);
    }
}
