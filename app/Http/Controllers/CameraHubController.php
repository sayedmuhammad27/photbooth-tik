<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CameraHubController extends Controller
{
    /**
     * Halaman Admin untuk menyiarkan kamera yang tercolok di perangkat admin ke bilik user.
     */
    public function index()
    {
        session(['digicam_ok' => true]);

        return view('admin.camera_hub', [
            'digicam' => [
                'enabled'        => (bool) config('photobooth.digicam.enabled'),
                'status_url'     => route('digicam.status', [], false),
                'liveview_url'   => route('digicam.liveview', [], false),
                'live_start_url' => route('digicam.liveview.start', [], false),
                'live_stop_url'  => route('digicam.liveview.stop', [], false),
                'capture_url'    => route('digicam.capture', [], false),
                'interval'       => max(60, (int) config('photobooth.digicam.live_interval', 120)),
            ],
        ]);
    }

    /**
     * Browser admin mengirimkan frame live view ke server.
     */
    public function pushFrame(Request $request): JsonResponse
    {
        if (session()->isStarted()) {
            session()->save();
        }

        $frame = $request->input('frame'); // Base64 dataURL

        if (! $frame) {
            return response()->json(['ok' => false], 422);
        }

        // Simpan langsung binary file di disk untuk serving instan tanpa overhead serialisasi
        $parts = explode(',', $frame, 2);
        if (isset($parts[1])) {
            $binary = base64_decode($parts[1]);
            @file_put_contents(storage_path('framework/cache/hub_frame.jpg'), $binary);
        }

        Cache::put('camera_hub_frame', $frame, now()->addSeconds(10));
        Cache::put('camera_hub_last_seen', now()->timestamp, now()->addSeconds(10));

        // Cek apakah ada permintaan capture dari bilik user
        $hasTrigger = Cache::pull('camera_hub_trigger_capture', false);

        return response()->json([
            'ok'      => true,
            'trigger' => (bool) $hasTrigger,
        ]);
    }

    /**
     * Browser user (kiosk di bilik foto) mengambil frame live view dari kamera admin.
     */
    public function getFrame(): Response|JsonResponse
    {
        if (session()->isStarted()) {
            session()->save();
        }

        $filePath = storage_path('framework/cache/hub_frame.jpg');

        if (file_exists($filePath) && (time() - filemtime($filePath) < 10)) {
            $binary = @file_get_contents($filePath);
            if ($binary) {
                return response($binary, 200, [
                    'Content-Type'  => 'image/jpeg',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }
        }

        $dataUrl = Cache::get('camera_hub_frame');

        if (! $dataUrl) {
            return response()->json(['message' => 'Kamera admin tidak sedang menyiarkan.'], 503);
        }

        // Pisahkan base64 header
        $parts = explode(',', $dataUrl, 2);
        $binary = base64_decode($parts[1] ?? '');

        return response($binary, 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * User di bilik menekan tombol foto -> kirim sinyal trigger ke kamera admin.
     */
    public function triggerCapture(): JsonResponse
    {
        $captureId = Str::random(12);
        Cache::put('camera_hub_trigger_capture', $captureId, now()->addSeconds(15));
        Cache::put('camera_hub_pending_capture_id', $captureId, now()->addSeconds(15));

        return response()->json([
            'ok'         => true,
            'capture_id' => $captureId,
        ]);
    }

    /**
     * Browser admin mengirimkan foto resolusi penuh hasil capture.
     */
    public function pushSnapshot(Request $request): JsonResponse
    {
        $snapshot = $request->input('snapshot'); // Base64 dataURL
        $captureId = $request->input('capture_id');

        if (! $snapshot) {
            return response()->json(['ok' => false], 422);
        }

        Cache::put('camera_hub_snapshot_' . $captureId, $snapshot, now()->addMinutes(2));
        Cache::put('camera_hub_latest_snapshot', $snapshot, now()->addMinutes(2));

        return response()->json(['ok' => true]);
    }

    /**
     * User di bilik mengambil foto resolusi penuh yang baru saja dijepret oleh kamera admin.
     */
    public function getSnapshot(Request $request): Response|JsonResponse
    {
        $captureId = $request->query('capture_id');

        $dataUrl = $captureId
            ? Cache::get('camera_hub_snapshot_' . $captureId)
            : Cache::get('camera_hub_latest_snapshot');

        if (! $dataUrl) {
            return response()->json(['message' => 'Foto belum siap.'], 404);
        }

        $parts = explode(',', $dataUrl);
        $binary = base64_decode($parts[1] ?? '');

        return response($binary, 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * Status ketersediaan kamera admin.
     */
    public function status(): JsonResponse
    {
        $lastSeen = Cache::get('camera_hub_last_seen');
        $isLive = $lastSeen && (now()->timestamp - $lastSeen < 6);

        return response()->json([
            'hub_active'     => $isLive,
            'liveview_url'   => route('camera.hub.frame'),
            'capture_url'    => route('camera.hub.trigger'),
            'snapshot_url'   => route('camera.hub.snapshot'),
            'digicam_active' => (bool) config('photobooth.digicam.enabled'),
        ]);
    }
}
