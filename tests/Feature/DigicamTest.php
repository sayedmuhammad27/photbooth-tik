<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigicamTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG = "\xFF\xD8\xFF\xE0FAKEJPEGDATA";

    protected function setUp(): void
    {
        parent::setUp();
        config(['photobooth.digicam.enabled' => true, 'photobooth.digicam.url' => 'http://127.0.0.1:5513']);
    }

    /** Meniru web server digiCamControl. $captured = balasan berurutan untuk "lastcaptured". */
    private function fakeDcc(array $captured, string $image = self::JPEG): void
    {
        Http::fake(function ($request) use (&$captured, $image) {
            $url = $request->url();
            if (str_contains($url, '/session.json')) {
                return Http::response('{}', 200);
            }
            if (str_contains($url, 'param1=lastcaptured')) {
                $next = count($captured) > 1 ? array_shift($captured) : $captured[0];

                return Http::response($next, 200);
            }
            if (str_contains($url, 'slc=capture')) {
                return Http::response('OK', 200);
            }
            if (str_contains($url, '/image/')) {
                return Http::response($image, 200, ['Content-Type' => 'image/jpeg']);
            }
            if (str_contains($url, 'liveview.jpg')) {
                return Http::response($image, 200);
            }
            if (str_contains($url, 'CMD=LiveViewWnd')) {
                return Http::response('OK', 200);
            }

            return Http::response('', 404);
        });
    }

    private function makeTemplate(): Template
    {
        return Template::create([
            'name' => 'Uji', 'category' => 'single', 'slot_count' => 1, 'bg_color' => '#131947',
            'is_active' => true, 'sort_order' => 1,
            'layout_config' => [['x' => 50, 'y' => 260, 'width' => 1100, 'height' => 1200, 'photo' => 0]],
        ]);
    }

    public function test_endpoints_return_404_when_feature_disabled(): void
    {
        config(['photobooth.digicam.enabled' => false]);

        $this->withSession(['digicam_ok' => true])->getJson('/digicam/status')->assertNotFound();
        $this->withSession(['digicam_ok' => true])->postJson('/digicam/capture')->assertNotFound();
    }

    public function test_endpoints_require_a_session_that_opened_the_camera_page(): void
    {
        $this->getJson('/digicam/status')->assertForbidden();
        $this->get('/digicam/liveview')->assertForbidden();
        $this->postJson('/digicam/capture')->assertForbidden();
    }

    public function test_camera_page_exposes_digicam_config_and_marks_the_session(): void
    {
        $t = $this->makeTemplate();

        $response = $this->get(route('photobooth.session', $t))->assertOk();

        $response->assertSessionHas('digicam_ok', true);
        $cfg = $response->viewData('boothConfig')['digicam'];
        $this->assertSame('/digicam/capture', $cfg['capture_url']);
        $this->assertSame('digicam', $cfg['default_source']);
    }

    public function test_camera_page_has_no_digicam_config_when_disabled(): void
    {
        config(['photobooth.digicam.enabled' => false]);
        $t = $this->makeTemplate();

        $response = $this->get(route('photobooth.session', $t))->assertOk();

        $this->assertNull($response->viewData('boothConfig')['digicam']);
    }

    public function test_status_reports_ok_when_digicamcontrol_answers(): void
    {
        $this->fakeDcc(['']);

        $this->withSession(['digicam_ok' => true])->getJson('/digicam/status')
            ->assertOk()->assertJson(['ok' => true]);
    }

    public function test_status_reports_failure_when_digicamcontrol_is_down(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->withSession(['digicam_ok' => true])->getJson('/digicam/status')
            ->assertOk()->assertJson(['ok' => false]);
    }

    public function test_liveview_returns_the_jpeg_frame(): void
    {
        $this->fakeDcc(['']);

        $res = $this->withSession(['digicam_ok' => true])->get('/digicam/liveview')->assertOk();

        $this->assertSame(self::JPEG, $res->getContent());
        $this->assertStringContainsString('image/jpeg', $res->headers->get('Content-Type'));
    }

    public function test_liveview_gives_503_when_not_a_jpeg(): void
    {
        Http::fake(['*' => Http::response('<html>not started</html>', 200)]);

        $this->withSession(['digicam_ok' => true])->getJson('/digicam/liveview')->assertStatus(503);
    }

    public function test_capture_waits_for_the_new_file_and_returns_the_photo(): void
    {
        config(['photobooth.digicam.capture_timeout' => 8]);
        // before = DSC_0001.JPG; lalu "-" (sedang memotret); lalu file baru.
        $this->fakeDcc(['DSC_0001.JPG', '-', 'DSC_0002.JPG']);

        $res = $this->withSession(['digicam_ok' => true])->post('/digicam/capture')->assertOk();

        $this->assertSame(self::JPEG, $res->getContent());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/image/DSC_0002.JPG'));
    }

    public function test_capture_rejects_raw_files(): void
    {
        config(['photobooth.digicam.capture_timeout' => 8]);
        $this->fakeDcc(['IMG_0001.CR2', 'IMG_0002.CR2']);

        $this->withSession(['digicam_ok' => true])->postJson('/digicam/capture')
            ->assertStatus(422)->assertJsonFragment(['message' => 'File hasil bukan JPEG (IMG_0002.CR2). Ubah kualitas gambar kamera ke JPEG, bukan RAW.']);
    }

    public function test_capture_never_requests_paths_outside_image_endpoint(): void
    {
        config(['photobooth.digicam.capture_timeout' => 8]);
        $this->fakeDcc(['a.jpg', '..\\..\\Windows\\win.jpg']);

        $this->withSession(['digicam_ok' => true])->post('/digicam/capture');

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '..'));
    }
}
