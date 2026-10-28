<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class TestPhotoController extends Controller
{
    /**
     * Galeri template khusus mode test foto.
     */
    public function setup()
    {
        $templates = Template::active()
            ->ordered()
            ->get()
            ->map(function (Template $t) {
                $arr = $t->toBoothArray();
                $arr['session_url'] = route('test.photo.session', $t);
                return $arr;
            })
            ->values();

        return view('photobooth.setup', [
            'templates' => $templates,
            'isTestMode' => true,
        ]);
    }

    /**
     * Bilik kamera khusus test foto (tanpa perlu antrean).
     */
    public function create(Template $template)
    {
        if (! $template->is_active) {
            return redirect()->route('test.photo.setup')
                ->with('error', 'Template tersebut sedang tidak aktif. Pilih template lain.');
        }

        $digicam = null;
        if (config('photobooth.digicam.enabled')) {
            session(['digicam_ok' => true]);
            $digicam = [
                'status_url'     => route('digicam.status', [], false),
                'liveview_url'   => route('digicam.liveview', [], false),
                'live_start_url' => route('digicam.liveview.start', [], false),
                'live_stop_url'  => route('digicam.liveview.stop', [], false),
                'capture_url'    => route('digicam.capture', [], false),
                'interval'       => max(60, (int) config('photobooth.digicam.live_interval', 120)),
                'default_source' => config('photobooth.digicam.default_source', 'digicam') === 'webcam' ? 'webcam' : 'digicam',
            ];
        }

        return view('photobooth.camera', [
            'template'    => $template,
            'isTestMode'  => true,
            'boothConfig' => [
                'template'     => $template->toBoothArray(),
                'process_url'  => route('test.photo.process', $template),
                'token_url'    => route('csrf'),
                'csrf'         => csrf_token(),
                'countdown'    => (int) config('photobooth.countdown', 3),
                'advance_mode' => config('photobooth.advance_mode', 'manual'),
                'digicam'      => $digicam,
                'camera_hub'   => [
                    'status_url'   => route('camera.hub.status', [], false),
                    'liveview_url' => route('camera.hub.frame', [], false),
                    'trigger_url'  => route('camera.hub.trigger', [], false),
                    'snapshot_url' => route('camera.hub.snapshot', [], false),
                ],
                'is_test_mode' => true,
            ],
        ]);
    }

    /**
     * Proses foto uji coba (test foto).
     * Foto digabung ke template, disimpan di disk, TETAPI TIDAK disimpan ke database photo_sessions.
     */
    public function process(Request $request, Template $template): JsonResponse
    {
        abort_unless($template->is_active, 404);

        $slotCount = (int) $template->slot_count;

        $request->validate([
            'photos'   => ['required', 'array', 'size:' . $slotCount],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:12288'],
        ]);

        $files = array_values($request->file('photos'));
        $token = 'test_' . Str::random(20);
        $disk = Storage::disk('public');
        $written = [];

        try {
            $width = Template::CANVAS_WIDTH;
            $height = Template::CANVAS_HEIGHT;

            $manager = new ImageManager(new Driver());
            $canvas = $manager->create($width, $height)->fill($template->bg_color ?: '#131947');

            foreach ($template->normalizedSlots() as $slot) {
                $file = $files[$slot['photo']] ?? null;
                if ($file === null) {
                    continue;
                }
                $photo = $manager->read($file->getRealPath())->cover($slot['width'], $slot['height']);
                $canvas->place($photo, 'top-left', $slot['x'], $slot['y']);
            }

            if ($template->frame_asset && $disk->exists($template->frame_asset)) {
                $frame = $manager->read($disk->path($template->frame_asset));
                if ($frame->width() !== $width || $frame->height() !== $height) {
                    $frame = $frame->resize($width, $height);
                }
                $canvas->place($frame, 'top-left', 0, 0);
            }

            $finalPath = "results/{$token}_final.jpg";
            $disk->put($finalPath, (string) $canvas->toJpeg(92));
            $written[] = $finalPath;

            // Simpan foto satuan test
            $rawPaths = [];
            foreach ($files as $i => $file) {
                $ext = $file->guessExtension() === 'png' ? 'png' : 'jpg';
                $stored = $disk->putFileAs('results', $file, "{$token}_raw_" . ($i + 1) . ".{$ext}");
                if ($stored !== false) {
                    $written[] = $stored;
                    $rawPaths[] = $stored;
                }
            }

            // Simpan metadata test di session (TIDAK KE DATABASE!)
            session([
                'test_photo_' . $token => [
                    'final_path' => $finalPath,
                    'raw_paths'  => $rawPaths,
                    'template'   => $template->name,
                    'created_at' => now()->format('H:i:s'),
                ],
            ]);

            return response()->json([
                'redirect' => route('test.photo.result', $token),
            ]);
        } catch (Throwable $e) {
            foreach ($written as $path) {
                $disk->delete($path);
            }
            Log::error('Test photo gagal diproses', ['exception' => $e]);

            return response()->json([
                'message' => 'Gagal memproses foto test. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Halaman hasil test foto (hanya sementara di session/disk, tidak di database).
     */
    public function result(string $token)
    {
        $finalPath = "results/{$token}_final.jpg";
        $disk = Storage::disk('public');

        abort_unless($disk->exists($finalPath), 404);

        $finalUrl = route('media', ['path' => $finalPath], false);
        $metadata = session('test_photo_' . $token, []);

        $rawUrls = [];
        if (! empty($metadata['raw_paths'])) {
            foreach ($metadata['raw_paths'] as $rp) {
                if ($disk->exists($rp)) {
                    $rawUrls[] = route('media', ['path' => $rp], false);
                }
            }
        }

        return view('photobooth.test_result', [
            'token'     => $token,
            'finalUrl'  => $finalUrl,
            'rawUrls'   => $rawUrls,
            'metadata'  => $metadata,
        ]);
    }

    /**
     * Unduh hasil test foto.
     */
    public function download(string $token): BinaryFileResponse
    {
        $finalPath = "results/{$token}_final.jpg";
        $disk = Storage::disk('public');

        abort_unless($disk->exists($finalPath), 404);

        return response()->download($disk->path($finalPath), "photobooth-test-{$token}.jpg");
    }
}
