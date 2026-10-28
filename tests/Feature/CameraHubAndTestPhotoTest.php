<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CameraHubAndTestPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_camera_hub_admin_can_access_broadcaster_page(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/camera-hub');
        $response->assertStatus(200);
        $response->assertSee('KAMERA BILIK (SIARKAN KAMERA ADMIN)');
        $response->assertSee('Mulai Siaran Kamera');
        $response->assertSee('STATUS DIGICAMCONTROL');
        $response->assertSee('Test Jepret Canon');
        $response->assertSessionHas('digicam_ok', true);
    }

    public function test_camera_hub_push_and_get_frame(): void
    {
        $admin = User::factory()->create();

        $jpegData = 'data:image/jpeg;base64,' . base64_encode(str_repeat('A', 100));

        $push = $this->actingAs($admin)->postJson('/camera-hub/push', [
            'frame' => $jpegData,
        ]);
        $push->assertStatus(200);
        $push->assertJson(['ok' => true]);

        $frame = $this->get('/camera-hub/frame');
        $frame->assertStatus(200);
        $this->assertEquals('image/jpeg', $frame->headers->get('Content-Type'));
    }

    public function test_camera_hub_snapshot_trigger_and_poll(): void
    {
        // 1. Bilik user meminta capture snapshot
        $trigger = $this->postJson('/camera-hub/trigger');
        $trigger->assertStatus(200);
        $captureId = $trigger->json('capture_id');
        $this->assertNotEmpty($captureId);

        // 2. Hub admin menerima status trigger saat push frame
        $admin = User::factory()->create();
        $pushFrame = $this->actingAs($admin)->postJson('/camera-hub/push', [
            'frame' => 'data:image/jpeg;base64,' . base64_encode(str_repeat('A', 100)),
        ]);
        $pushFrame->assertStatus(200);
        $this->assertTrue($pushFrame->json('trigger'));

        // 3. Hub admin mengirim foto snapshot beresolusi penuh
        $pushSnap = $this->actingAs($admin)->postJson('/camera-hub/snapshot/push', [
            'capture_id' => $captureId,
            'snapshot'   => 'data:image/jpeg;base64,' . base64_encode(str_repeat('B', 150)),
        ]);
        $pushSnap->assertStatus(200);

        // 4. Bilik mengambil snapshot yang sudah siap
        $getSnap = $this->get('/camera-hub/snapshot?capture_id=' . $captureId);
        $getSnap->assertStatus(200);
        $this->assertEquals('image/jpeg', $getSnap->headers->get('Content-Type'));
    }

    public function test_test_photo_flow_does_not_save_to_database(): void
    {
        Storage::fake('public');

        $template = Template::create([
            'name'         => 'Test Frame Strip',
            'layout_type'  => 'strip_3',
            'slot_count'   => 2,
            'is_active'    => true,
            'layout_config'=> [
                'canvas' => ['width' => 800, 'height' => 1200],
                'slots'  => [
                    ['x' => 50, 'y' => 50, 'w' => 700, 'h' => 500],
                    ['x' => 50, 'y' => 600, 'w' => 700, 'h' => 500],
                ],
            ],
        ]);

        // Akses setup test
        $resSetup = $this->get('/test-photo');
        $resSetup->assertStatus(200);
        $resSetup->assertSee('MODE TEST FOTO');

        // Akses camera test session
        $resCam = $this->get('/test-photo/session/' . $template->id);
        $resCam->assertStatus(200);
        $resCam->assertSee('MODE TEST FOTO');

        // Buat file gambar palsu
        $file1 = UploadedFile::fake()->image('p1.jpg', 600, 400);
        $file2 = UploadedFile::fake()->image('p2.jpg', 600, 400);

        // Kirim proses test photo
        $resProcess = $this->post('/test-photo/session/' . $template->id . '/process', [
            'photos' => [$file1, $file2],
        ]);

        $resProcess->assertStatus(200);
        $redirectUrl = $resProcess->json('redirect');
        $this->assertNotEmpty($redirectUrl);
        $token = basename(parse_url($redirectUrl, PHP_URL_PATH));
        $this->assertStringStartsWith('test_', $token);

        // Pastikan tidak ada data sesi di tabel photo_sessions
        $this->assertDatabaseCount('photo_sessions', 0);

        // Cek halaman hasil test
        $resResult = $this->get('/test-photo/result/' . $token);
        $resResult->assertStatus(200);
        $resResult->assertSee('Hasil Test Foto');
        $resResult->assertSee('TIDAK disimpan ke database');
    }
}
