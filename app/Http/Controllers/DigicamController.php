<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Jembatan antara browser dan web server digiCamControl (kamera Canon via EDSDK).
 *
 * digiCamControl harus berjalan di laptop yang sama dengan server Laravel, dengan
 * web server aktif (File → Settings → Webserver, port 5513). Browser TIDAK memanggil
 * digiCamControl langsung, jadi tidak ada masalah CORS atau campuran HTTP/HTTPS.
 *
 * Perintah yang dipakai (dokumentasi digiCamControl "Web Interface"):
 *   /?CMD=LiveViewWnd_Show | _Hide   mulai / hentikan live view
 *   /liveview.jpg                    frame live view saat ini
 *   /?slc=capture                    ambil foto
 *   /?slc=get&param1=lastcaptured    nama file foto terakhir ("-" bila masih berjalan)
 *   /image/{nama}                    unduh foto hasil
 */
class DigicamController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $this->guard($request);

        try {
            $this->client(2)->get($this->base() . '/session.json')->throw();
        } catch (Throwable $e) {
            Log::warning('digiCamControl tidak terjangkau', ['error' => $e->getMessage()]);

            return response()->json([
                'ok'      => false,
                'message' => 'digiCamControl tidak terjangkau di ' . $this->base() . '. Pastikan aplikasinya berjalan dan web server-nya aktif.',
            ]);
        }

        // Info kamera hanya sebagai keterangan (format balasan bisa berbeda antar versi), tidak menentukan "ok".
        $cameras = '';
        try {
            $cameras = mb_substr($this->command('list', 'cameras', '', 3), 0, 200);
        } catch (Throwable $e) {
            // abaikan
        }

        return response()->json(['ok' => true, 'cameras' => $cameras]);
    }

    public function liveviewStart(Request $request): JsonResponse
    {
        $this->guard($request);

        try {
            $this->client(5)->get($this->base() . '/', ['CMD' => 'LiveViewWnd_Show'])->throw();
        } catch (Throwable $e) {
            return $this->down($e);
        }

        return response()->json(['ok' => true]);
    }

    public function liveviewStop(Request $request): JsonResponse
    {
        $this->guard($request);

        try {
            $this->client(5)->get($this->base() . '/', ['CMD' => 'LiveViewWnd_Hide'])->throw();
        } catch (Throwable $e) {
            return $this->down($e);
        }

        return response()->json(['ok' => true]);
    }

    /** Satu frame live view (JPEG). Browser memanggil ini berulang kali. */
    public function liveview(Request $request): Response|JsonResponse
    {
        $this->guard($request);

        // Bebaskan session lock segera agar tidak memblokir request lain dari browser yang sama
        if (session()->isStarted()) {
            session()->save();
        }

        try {
            $res = $this->client(3)->get($this->base() . '/liveview.jpg');
        } catch (Throwable $e) {
            return $this->down($e);
        }

        $body = $res->body();
        if (! $res->successful() || ! $this->isJpeg($body)) {
            return response()->json(['message' => 'Live view belum aktif di digiCamControl.'], 503);
        }

        return response($body, 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /** Potret lalu kembalikan foto resolusi penuh (JPEG). */
    public function capture(Request $request): Response|JsonResponse
    {
        $this->guard($request);

        // Bebaskan session lock segera agar request lain (seperti polling bilik) tidak terblokir
        if (session()->isStarted()) {
            session()->save();
        }

        $timeout = max(5, (int) config('photobooth.digicam.capture_timeout', 25));
        @set_time_limit($timeout + 40);

        $lock = Cache::lock('digicam-capture', $timeout + 20);
        if (! $lock->get()) {
            return response()->json(['message' => 'Pemotretan lain sedang berjalan. Coba lagi sebentar.'], 409);
        }

        try {
            $before = $this->lastCaptured();

            $reply = $this->command('capture', '', '', 20);
            if ($reply !== '' && preg_match('/error|fail|no camera|not connected|exception/i', $reply) === 1) {
                return response()->json(['message' => 'digiCamControl: ' . mb_substr($reply, 0, 160)], 502);
            }

            // Tunggu nama file baru. "-" berarti pemotretan masih berjalan.
            $deadline = microtime(true) + $timeout;
            $sawBusy = false;
            $name = null;
            while (microtime(true) < $deadline) {
                usleep(150000);
                $current = $this->lastCaptured();
                if ($current === '-') {
                    $sawBusy = true;
                    continue;
                }
                if ($current !== '' && ($current !== $before || $sawBusy)) {
                    $name = $current;
                    break;
                }
            }

            if ($name === null) {
                return response()->json([
                    'message' => 'Kamera tidak merespons. Periksa kamera menyala, fokus bisa terkunci, dan tidak ada aplikasi Canon lain yang memakainya.',
                ], 504);
            }

            if (preg_match('/\.jpe?g$/i', $name) !== 1) {
                return response()->json([
                    'message' => 'File hasil bukan JPEG (' . mb_substr($name, 0, 40) . '). Ubah kualitas gambar kamera ke JPEG, bukan RAW.',
                ], 422);
            }
            if (preg_match('/^[\w .()\-]+$/u', $name) !== 1) {
                return response()->json(['message' => 'Nama file dari digiCamControl tidak dikenali.'], 502);
            }

            $bytes = $this->fetchImage($name);
            if ($bytes === null) {
                return response()->json([
                    'message' => 'Foto sudah diambil, tetapi file tidak bisa diunduh dari digiCamControl.',
                ], 502);
            }

            return response($bytes, 200, [
                'Content-Type'  => 'image/jpeg',
                'Cache-Control' => 'no-store',
            ]);
        } catch (Throwable $e) {
            return $this->down($e);
        } finally {
            $lock->release();
        }
    }

    /* ------------------------------------------------------------------ */

    private function guard(Request $request): void
    {
        abort_unless(config('photobooth.digicam.enabled'), 404);
        abort_unless($request->session()->get('digicam_ok') === true || auth()->check(), 403);
    }

    private function base(): string
    {
        return rtrim((string) config('photobooth.digicam.url'), '/');
    }

    private function client(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->connectTimeout(2);
    }

    /** Perintah "Single Command System": /?slc=...&param1=...&param2=... */
    private function command(string $slc, string $p1 = '', string $p2 = '', int $timeout = 5): string
    {
        $res = $this->client($timeout)->get($this->base() . '/', [
            'slc'    => $slc,
            'param1' => $p1,
            'param2' => $p2,
        ]);

        return trim($res->body());
    }

    /** Nama file foto terakhir, '-' bila pemotretan sedang berjalan, '' bila belum ada. */
    private function lastCaptured(): string
    {
        $raw = $this->command('get', 'lastcaptured', '', 4);
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $raw) ?: []),
            fn ($l) => $l !== ''
        ));

        $line = $lines === [] ? '' : trim((string) end($lines), " \t\"'");
        if ($line === '' || strcasecmp($line, 'OK') === 0) {
            return '';
        }
        if ($line === '-') {
            return '-';
        }

        $parts = preg_split('#[\\\\/]#', $line) ?: [];

        return $parts === [] ? '' : (string) end($parts);   // jika balasan berupa path penuh, ambil nama berkasnya
    }

    private function fetchImage(string $name): ?string
    {
        $until = microtime(true) + 15;
        while (microtime(true) < $until) {
            try {
                $res = $this->client(20)->get($this->base() . '/image/' . rawurlencode($name));
                if ($res->successful() && $this->isJpeg($res->body())) {
                    return $res->body();
                }
            } catch (Throwable $e) {
                // file mungkin belum selesai ditulis; coba lagi
            }
            usleep(150000);
        }

        return null;
    }

    private function isJpeg(string $b): bool
    {
        return strlen($b) > 4 && $b[0] === "\xFF" && $b[1] === "\xD8";
    }

    private function down(Throwable $e): JsonResponse
    {
        Log::warning('digiCamControl tidak terjangkau', ['error' => $e->getMessage()]);

        return response()->json([
            'message' => 'digiCamControl tidak terjangkau. Pastikan aplikasinya berjalan dan web server-nya aktif (File → Settings → Webserver, port 5513).',
        ], 503);
    }
}
