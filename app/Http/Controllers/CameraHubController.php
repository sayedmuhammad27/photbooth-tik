<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
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
     * Mendukung raw binary (Blob) langsung untuk kecepatan maksimal dan CPU hemat,
     * serta fallback Base64 jika dikirim sebagai JSON.
     */
    public function pushFrame(Request $request): JsonResponse
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $filePath = storage_path('framework/cache/hub_frame.jpg');

        // 1. Cek apakah dikirim sebagai binary langsung (Content-Type: image/jpeg)
        $contentType = $request->header('Content-Type', '');
        if (str_contains($contentType, 'image/') || str_contains($contentType, 'application/octet-stream')) {
            $binary = $request->getContent();
            if ($binary && strlen($binary) > 200) {
                @file_put_contents($filePath, $binary);
            }
        } elseif ($request->hasFile('frame')) {
            $file = $request->file('frame');
            if ($file && $file->isValid()) {
                @copy($file->getRealPath(), $filePath);
            }
        } else {
            // 2. Fallback Base64 JSON
            $frame = $request->input('frame');
            if ($frame) {
                $parts = explode(',', $frame, 2);
                if (isset($parts[1])) {
                    $binary = base64_decode($parts[1]);
                    @file_put_contents($filePath, $binary);
                }
            }
        }

        Cache::put('camera_hub_last_seen', now()->timestamp, now()->addSeconds(10));

        // Cek apakah ada permintaan jepret dari bilik user
        $triggerId = Cache::pull('camera_hub_trigger_capture', false);

        return response()->json([
            'ok'         => true,
            'trigger'    => (bool) $triggerId,
            'capture_id' => $triggerId ?: null,
        ]);
    }

    /**
     * Browser user (kiosk di bilik foto) mengambil frame live view dari kamera admin.
     * Menggunakan binary stream langsung tanpa beban serialisasi JSON/Base64.
     */
    public function getFrame(): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $filePath = storage_path('framework/cache/hub_frame.jpg');

        if (file_exists($filePath) && (time() - filemtime($filePath) < 10)) {
            return response()->file($filePath, [
                'Content-Type'  => 'image/jpeg',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
            ]);
        }

        return response()->json(['message' => 'Kamera admin tidak sedang menyiarkan.'], 503);
    }

    /**
     * User di bilik menekan tombol foto -> kirim sinyal trigger ke kamera admin.
     */
    public function triggerCapture(): JsonResponse
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $captureId = Str::random(12);
        Cache::put('camera_hub_trigger_capture', $captureId, now()->addSeconds(20));
        Cache::put('camera_hub_pending_capture_id', $captureId, now()->addSeconds(20));

        return response()->json([
            'ok'         => true,
            'capture_id' => $captureId,
        ]);
    }

    /**
     * Browser admin mengirimkan foto resolusi penuh hasil capture.
     * Mendukung raw binary blob maupun Base64.
     */
    public function pushSnapshot(Request $request): JsonResponse
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $captureId = $request->input('capture_id')
            ?? $request->header('X-Capture-Id')
            ?? Cache::get('camera_hub_pending_capture_id');

        $contentType = $request->header('Content-Type', '');
        $saved = false;

        // Cek binary stream
        if (str_contains($contentType, 'image/') || str_contains($contentType, 'application/octet-stream')) {
            $binary = $request->getContent();
            if ($binary && strlen($binary) > 1000) {
                $dir = storage_path('framework/cache/snapshots');
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $path = $dir . '/snap_' . ($captureId ?: 'latest') . '.jpg';
                @file_put_contents($path, $binary);
                $saved = true;
            }
        } elseif ($request->hasFile('snapshot')) {
            $file = $request->file('snapshot');
            if ($file && $file->isValid()) {
                $dir = storage_path('framework/cache/snapshots');
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $path = $dir . '/snap_' . ($captureId ?: 'latest') . '.jpg';
                @copy($file->getRealPath(), $path);
                $saved = true;
            }
        } else {
            // Fallback base64
            $snapshot = $request->input('snapshot');
            if ($snapshot) {
                $parts = explode(',', $snapshot, 2);
                $binary = base64_decode($parts[1] ?? $parts[0]);
                $dir = storage_path('framework/cache/snapshots');
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $path = $dir . '/snap_' . ($captureId ?: 'latest') . '.jpg';
                @file_put_contents($path, $binary);
                $saved = true;
            }
        }

        if (! $saved) {
            return response()->json(['ok' => false, 'message' => 'Format foto tidak valid.'], 422);
        }

        if ($captureId) {
            Cache::put('camera_hub_snap_ready_' . $captureId, true, now()->addMinutes(2));
        }
        Cache::put('camera_hub_snap_ready_latest', true, now()->addMinutes(2));

        return response()->json(['ok' => true]);
    }

    /**
     * User di bilik mengambil foto resolusi penuh yang baru saja dijepret oleh kamera admin.
     */
    public function getSnapshot(Request $request): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $captureId = $request->query('capture_id');
        $dir = storage_path('framework/cache/snapshots');

        $path = $captureId ? ($dir . '/snap_' . $captureId . '.jpg') : null;

        if (! $path || ! file_exists($path)) {
            // Coba snapshot latest jika capture_id spesifik belum ada
            $latestPath = $dir . '/snap_latest.jpg';
            if (file_exists($latestPath) && (time() - filemtime($latestPath) < 60)) {
                $path = $latestPath;
            } else {
                return response()->json(['message' => 'Foto belum siap.'], 404);
            }
        }

        return response()->file($path, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Sinyal WebRTC Peer-to-Peer untuk streaming video real-time 30 FPS tanpa membebani server PHP.
     */
    public function signal(Request $request): JsonResponse
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $action = $request->input('action') ?? $request->query('action');

        switch ($action) {
            case 'register_host':
                // Admin mendaftarkan diri sebagai host siaran WebRTC
                Cache::put('webrtc_host_active', now()->timestamp, now()->addSeconds(15));
                return response()->json(['ok' => true]);

            case 'check_viewers':
                // Admin memeriksa apakah ada bilik user yang baru bergabung meminta koneksi video
                Cache::put('webrtc_host_active', now()->timestamp, now()->addSeconds(15));
                $viewers = Cache::get('webrtc_pending_viewers', []);
                return response()->json(['ok' => true, 'viewers' => array_values($viewers)]);

            case 'join':
                // Bilik user bergabung dan meminta stream video dari host
                $viewerId = (string) $request->input('viewer_id');
                if (! $viewerId) {
                    return response()->json(['ok' => false, 'message' => 'viewer_id diperlukan'], 422);
                }
                $viewers = Cache::get('webrtc_pending_viewers', []);
                $viewers[$viewerId] = $viewerId;
                Cache::put('webrtc_pending_viewers', $viewers, now()->addSeconds(30));
                return response()->json(['ok' => true]);

            case 'send_offer':
                // Admin mengirimkan WebRTC Offer untuk bilik tertentu
                $viewerId = (string) $request->input('viewer_id');
                $offer = $request->input('offer');
                if ($viewerId && $offer) {
                    Cache::put('webrtc_offer_' . $viewerId, $offer, now()->addSeconds(30));
                    // Hapus dari antrean pending
                    $viewers = Cache::get('webrtc_pending_viewers', []);
                    unset($viewers[$viewerId]);
                    Cache::put('webrtc_pending_viewers', $viewers, now()->addSeconds(30));
                }
                return response()->json(['ok' => true]);

            case 'get_offer':
                // Bilik user mengambil WebRTC Offer dari admin
                $viewerId = (string) ($request->input('viewer_id') ?? $request->query('viewer_id'));
                $offer = $viewerId ? Cache::get('webrtc_offer_' . $viewerId) : null;
                return response()->json(['ok' => (bool) $offer, 'offer' => $offer]);

            case 'send_answer':
                // Bilik user mengirimkan WebRTC Answer kembali ke admin
                $viewerId = (string) $request->input('viewer_id');
                $answer = $request->input('answer');
                if ($viewerId && $answer) {
                    Cache::put('webrtc_answer_' . $viewerId, $answer, now()->addSeconds(30));
                }
                return response()->json(['ok' => true]);

            case 'get_answer':
                // Admin mengambil WebRTC Answer dari bilik user
                $viewerId = (string) ($request->input('viewer_id') ?? $request->query('viewer_id'));
                $answer = $viewerId ? Cache::pull('webrtc_answer_' . $viewerId) : null;
                return response()->json(['ok' => (bool) $answer, 'answer' => $answer]);

            case 'status':
            default:
                $hostLastSeen = Cache::get('webrtc_host_active');
                $isHostLive = $hostLastSeen && (now()->timestamp - $hostLastSeen < 12);
                return response()->json([
                    'ok'          => true,
                    'host_active' => (bool) $isHostLive,
                ]);
        }
    }

    /**
     * Status ketersediaan kamera admin.
     */
    public function status(): JsonResponse
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $lastSeen = Cache::get('camera_hub_last_seen');
        $isLive = $lastSeen && (now()->timestamp - $lastSeen < 8);

        $hostLastSeen = Cache::get('webrtc_host_active');
        $webrtcActive = $hostLastSeen && (now()->timestamp - $hostLastSeen < 12);

        return response()->json([
            'hub_active'     => $isLive || $webrtcActive,
            'webrtc_active'  => (bool) $webrtcActive,
            'liveview_url'   => route('camera.hub.frame'),
            'capture_url'    => route('camera.hub.trigger'),
            'snapshot_url'   => route('camera.hub.snapshot'),
            'signal_url'     => route('camera.hub.signal'),
            'digicam_active' => (bool) config('photobooth.digicam.enabled'),
        ]);
    }
}
