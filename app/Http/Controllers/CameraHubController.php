<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                @file_put_contents($filePath, $binary, LOCK_EX);
            }
        } elseif ($request->hasFile('frame')) {
            $file = $request->file('frame');
            if ($file && $file->isValid()) {
                @file_put_contents($filePath, file_get_contents($file->getRealPath()), LOCK_EX);
            }
        } else {
            // 2. Fallback Base64 JSON
            $frame = $request->input('frame');
            if ($frame) {
                $parts = explode(',', $frame, 2);
                if (isset($parts[1])) {
                    $binary = base64_decode($parts[1]);
                    @file_put_contents($filePath, $binary, LOCK_EX);
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

        // Only HTTP viewers renew this lease. WebRTC viewers stop requesting frames.
        // Throttle the cache write rather than writing once per frame.
        $lastRequest = Cache::get('camera_hub_relay_requested_at', 0);
        if (microtime(true) - $lastRequest >= 1) {
            Cache::put('camera_hub_relay_requested_at', microtime(true), 4);
        }

        $filePath = storage_path('framework/cache/hub_frame.jpg');

        if (file_exists($filePath) && (time() - filemtime($filePath) < 10)) {
            return $this->imageResponse($filePath);
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

        // Pastikan tidak ada berkas cache lama dengan ID ini
        $dir = storage_path('framework/cache/snapshots');
        $oldPath = $dir . '/snap_' . $captureId . '.jpg';
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }

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

        $dir = storage_path('framework/cache/snapshots');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Cek binary stream
        if (str_contains($contentType, 'image/') || str_contains($contentType, 'application/octet-stream')) {
            $binary = $request->getContent();
            if ($binary && strlen($binary) > 50) {
                if ($captureId) {
                    $path = $dir . '/snap_' . $captureId . '.jpg';
                    @file_put_contents($path, $binary, LOCK_EX);
                }
                @file_put_contents($dir . '/snap_latest.jpg', $binary, LOCK_EX);
                $saved = true;
            }
        } elseif ($request->hasFile('snapshot')) {
            $file = $request->file('snapshot');
            if ($file && $file->isValid()) {
                if ($captureId) {
                    $path = $dir . '/snap_' . $captureId . '.jpg';
                    @file_put_contents($path, file_get_contents($file->getRealPath()), LOCK_EX);
                }
                @file_put_contents($dir . '/snap_latest.jpg', file_get_contents($file->getRealPath()), LOCK_EX);
                $saved = true;
            }
        } else {
            // Fallback base64
            $snapshot = $request->input('snapshot');
            if ($snapshot) {
                $parts = explode(',', $snapshot, 2);
                $binary = base64_decode($parts[1] ?? $parts[0]);
                if ($binary && strlen($binary) > 50) {
                    if ($captureId) {
                        $path = $dir . '/snap_' . $captureId . '.jpg';
                        @file_put_contents($path, $binary, LOCK_EX);
                    }
                    @file_put_contents($dir . '/snap_latest.jpg', $binary, LOCK_EX);
                    $saved = true;
                }
            }
        }

        if (! $saved) {
            return response()->json(['ok' => false, 'message' => 'Format foto tidak valid.'], 422);
        }

        // Bersihkan berkas snapshot lama (> 10 menit) secara berkala
        if (random_int(1, 10) === 1) {
            $now = time();
            foreach (glob($dir . '/snap_*.jpg') ?: [] as $oldFile) {
                if ($now - @filemtime($oldFile) > 600) {
                    @unlink($oldFile);
                }
            }
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

        // Jika bilik meminta capture_id spesifik (selalu dikirim oleh booth.js pada tiap foto):
        // WAJIB hanya mengembalikan berkas foto dengan capture_id tersebut.
        // JANGAN PERNAH fallback ke snap_latest.jpg di sini, karena bila foto baru masih diproses kamera,
        // fallback ke snap_latest.jpg akan mengembalikan foto sebelumnya (foto 1), menyebabkan foto terduplikasi / double!
        if ($captureId) {
            $path = $dir . '/snap_' . $captureId . '.jpg';
            if (file_exists($path) && filesize($path) > 50) {
                return $this->imageResponse($path);
            }

            return response()->json(['message' => 'Foto belum siap.'], 404);
        }

        // Fallback hanya berlaku jika request sama sekali tidak membawa parameter capture_id
        $latestPath = $dir . '/snap_latest.jpg';
        if (file_exists($latestPath) && (time() - filemtime($latestPath) < 60) && filesize($latestPath) > 50) {
            return $this->imageResponse($latestPath);
        }

        return response()->json(['message' => 'Foto belum siap.'], 404);
    }

    /**
     * Sinyal WebRTC; video dikirim antar browser tanpa relay JPEG melalui PHP.
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
                return response()->json([
                    'ok' => true,
                    'viewers' => array_values($viewers),
                    'relay_needed' => Cache::has('camera_hub_relay_requested_at'),
                    // Keep capture control alive even when JPEG uploads are idle.
                    'capture_id' => Cache::pull('camera_hub_trigger_capture'),
                ]);

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

    private function imageResponse(string $path): Response
    {
        // Readers must share the writer's lock now that Apache serves requests concurrently.
        return response()->stream(function () use ($path) {
            $file = fopen($path, 'rb');
            if ($file === false) { return; }
            try {
                if (flock($file, LOCK_SH)) {
                    fpassthru($file);
                    flock($file, LOCK_UN);
                }
            } finally {
                fclose($file);
            }
        }, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
