<?php

namespace App\Http\Controllers;

use App\Models\PhotoSession;
use App\Models\QueueTicket;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class SessionController extends Controller
{
    /** Halaman kamera untuk template yang dipilih. */
    public function create(Request $request, Template $template)
    {
        if (! $template->is_active) {
            return redirect()->route('photobooth.setup')
                ->with('error', 'Template tersebut sedang tidak aktif. Pilih template lain.');
        }

        $queueNum = $request->input('queue') ?: session('active_queue_number');

        if (! app()->environment('testing') && ! auth()->check()) {
            if (! $queueNum) {
                return redirect()->route('home')
                    ->with('error', 'Sesi foto tidak bisa dimulai! Anda belum memiliki nomor antrean.');
            }

            $ticket = QueueTicket::today()->where('queue_number', $queueNum)->first();
            if (! $ticket || $ticket->status !== 'in_session') {
                return redirect()->route('home')
                    ->with('error', 'Sesi foto tidak bisa dimulai! Antrean #' . $queueNum . ' belum disetujui oleh admin untuk masuk sesi foto.');
            }
        }

        if ($queueNum) {
            session(['active_queue_number' => $queueNum]);
        }

        $host = $request->getHost();
        if (! $this->isLocalHost($host)) {
            $scheme = (str_contains($host, 'trycloudflare.com') || str_contains($host, 'ngrok')) ? 'https' : $request->getScheme();
            session(['active_public_url' => "{$scheme}://{$host}"]);
        }

        $digicam = null;
        if (config('photobooth.digicam.enabled')) {
            // Tanda bahwa browser ini memang membuka halaman kamera (syarat memakai endpoint /digicam/*).
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
            'boothConfig' => [
                'template'     => $template->toBoothArray(),
                'process_url'  => route('photobooth.process', $template),
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
                    'signal_url'   => route('camera.hub.signal', [], false),
                    'interval'     => max(40, (int) config('photobooth.camera_hub.live_interval', 75)),
                ],
                'queue_number' => $request->input('queue') ?: session('active_queue_number'),
            ],
        ]);
    }

    /**
     * Terima foto (multipart), susun ke template di server, simpan, balas JSON.
     * Foto sudah dipotong sesuai rasio slot oleh browser; server memotong ulang (cover)
     * ke ukuran slot tepat sehingga tidak pernah ada foto yang digepengkan.
     */
    public function process(Request $request, Template $template): JsonResponse
    {
        abort_unless($template->is_active, 404);

        $slotCount = (int) $template->slot_count;

        $request->validate([
            'photos'   => ['required', 'array', 'size:' . $slotCount],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:12288'],
        ], [
            'photos.required' => 'Foto belum dikirim.',
            'photos.array'    => 'Format foto tidak valid.',
            'photos.size'     => 'Jumlah foto harus tepat ' . $slotCount . ' untuk template ini.',
            'photos.*.image'  => 'Berkas harus berupa gambar.',
            'photos.*.mimes'  => 'Foto harus berformat JPG atau PNG.',
            'photos.*.max'    => 'Ukuran satu foto maksimal 12 MB.',
        ]);

        $files = array_values($request->file('photos'));
        $token = Str::random(24);
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

            $rawPaths = [];
            foreach ($files as $i => $file) {
                $ext = $file->guessExtension() === 'png' ? 'png' : 'jpg';
                $stored = $disk->putFileAs('results', $file, "{$token}_raw_" . ($i + 1) . ".{$ext}");
                if ($stored === false) {
                    throw new \RuntimeException('Gagal menyimpan foto satuan.');
                }
                $written[] = $stored;
                $rawPaths[] = $stored;
            }

            $session = DB::transaction(function () use ($template, $token, $finalPath, $rawPaths, $request) {
                $photoSession = PhotoSession::create([
                    'template_id'      => $template->id,
                    'session_token'    => $token,
                    'final_image_path' => $finalPath,
                    'raw_photos'       => $rawPaths,
                ]);

                // Sinkronisasi status antrean menjadi completed
                $activeQueueNum = $request->input('queue_number') ?: session('active_queue_number');
                $queueTicket = null;
                if ($activeQueueNum) {
                    $queueTicket = QueueTicket::today()->where('queue_number', $activeQueueNum)->first();
                }
                if (! $queueTicket) {
                    $queueTicket = QueueTicket::today()->inSession()->orderBy('queue_number')->first();
                }
                if ($queueTicket) {
                    $queueTicket->markCompleted($token);
                    session()->forget('active_queue_number');
                }

                return $photoSession;
            });

            return response()->json([
                'redirect' => route('photobooth.result', $session->session_token),
            ]);
        } catch (Throwable $e) {
            foreach ($written as $path) {
                $disk->delete($path);
            }
            Log::error('Photobooth: gagal memproses sesi foto', [
                'template_id' => $template->id,
                'exception'   => $e,
            ]);

            return response()->json([
                'message' => 'Server gagal menyusun foto. Foto kamu masih aman di halaman ini, silakan coba lagi.',
            ], 500);
        }
    }

    /** Halaman hasil (juga tujuan QR code). Akses lewat token acak 24 karakter. */
    public function result(string $token)
    {
        $session = PhotoSession::with('template')->where('session_token', $token)->firstOrFail();

        $shareUrl = $this->shareUrl($token);
        $host = strtolower((string) parse_url($shareUrl, PHP_URL_HOST));

        return view('photobooth.result', [
            'session'   => $session,
            'shareUrl'  => $shareUrl,
            'qrCode'    => QrCode::format('svg')->size(220)->margin(0)->errorCorrection('M')->generate($shareUrl),
            'qrIsLocal' => $this->isLoopback($host),
            'qrIsLan'   => $this->isLanIp($host),
        ]);
    }

    /** Unduh sebagai lampiran: kind = "strip" atau nomor foto satuan (mulai 1). */
    public function download(string $token, string $kind)
    {
        $session = PhotoSession::where('session_token', $token)->firstOrFail();
        $disk = Storage::disk('public');

        if ($kind === 'strip') {
            $path = $session->final_image_path;
            $name = "photobooth-tik-{$token}.jpg";
        } else {
            $raw = is_array($session->raw_photos) ? array_values($session->raw_photos) : [];
            $path = $raw[((int) $kind) - 1] ?? null;
            abort_if($path === null, 404);
            $name = "photobooth-tik-{$token}-foto{$kind}." . (pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        }

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $name);
    }

    private function shareUrl(string $token): string
    {
        $path = route('photobooth.result', $token, false);
        $requestHost = request()->getHost();

        // 1. Jika request yang sedang aktif adalah domain publik internet (Cloudflare / Ngrok / domain publik)
        if (! $this->isLocalOrLanHost($requestHost)) {
            $scheme = request()->getScheme();
            if (str_contains($requestHost, 'trycloudflare.com') || str_contains($requestHost, 'ngrok')) {
                $scheme = 'https';
            }
            return "{$scheme}://{$requestHost}{$path}";
        }

        // 2. Cek apakah ada file tunnel aktif yang dicatat oleh cloudflared
        $tunnelFile = storage_path('tunnel_url.txt');
        if (file_exists($tunnelFile)) {
            $tunnelUrl = trim((string) @file_get_contents($tunnelFile));
            if (! empty($tunnelUrl) && filter_var($tunnelUrl, FILTER_VALIDATE_URL)) {
                $tHost = parse_url($tunnelUrl, PHP_URL_HOST);
                if (! $this->isLocalOrLanHost($tHost)) {
                    return rtrim($tunnelUrl, '/') . $path;
                }
            }
        }

        // 3. Cek apakah ada session active_public_url yang pernah dicatat
        if (session('active_public_url') && ! $this->isLocalOrLanHost(parse_url(session('active_public_url'), PHP_URL_HOST))) {
            return rtrim(session('active_public_url'), '/') . $path;
        }

        // 4. Jika PHOTOBOOTH_PUBLIC_URL diatur dan bukan loopback/LAN
        $base = config('photobooth.public_url');
        if (! empty($base)) {
            $baseHost = parse_url($base, PHP_URL_HOST);
            if (! $this->isLocalOrLanHost($baseHost)) {
                return rtrim($base, '/') . $path;
            }
        }

        // 5. Jaringan Lokal (Wi-Fi / LAN):
        // Jika request dibuka langsung lewat IP LAN (misal HP membuka http://172.27.19.40:8000)
        if ($this->isLanIp($requestHost)) {
            return request()->getSchemeAndHttpHost() . $path;
        }

        // Jika request dibuka via loopback laptop (localhost / 127.0.0.1),
        // gunakan IP LAN server agar QR code bisa dipindai oleh HP pengunjung di Wi-Fi yang sama
        $detectedLanIp = $this->detectLocalIp();
        if ($detectedLanIp) {
            $port = request()->getPort();
            $portPart = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';
            return "http://{$detectedLanIp}{$portPart}{$path}";
        }

        // 6. Fallback ke URL request saat ini
        return request()->getSchemeAndHttpHost() . $path;
    }

    private function isLoopback(?string $host): bool
    {
        if (empty($host)) {
            return true;
        }

        $host = strtolower(trim($host));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }

    private function isLanIp(?string $host): bool
    {
        if (empty($host)) {
            return false;
        }

        $host = strtolower(trim($host));

        return preg_match('/^192\.168\./', $host) === 1
            || preg_match('/^10\./', $host) === 1
            || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) === 1;
    }

    private function isLocalOrLanHost(?string $host): bool
    {
        return $this->isLoopback($host) || $this->isLanIp($host);
    }

    private function isLocalHost(?string $host): bool
    {
        return $this->isLocalOrLanHost($host);
    }

    private function detectLocalIp(): ?string
    {
        // 1. Cek storage/local_ip.txt (ditulis otomatis saat start oleh run-tunnel.ps1 / run-lokal.ps1)
        $file = storage_path('local_ip.txt');
        if (file_exists($file)) {
            $ip = trim((string) @file_get_contents($file));
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->isLanIp($ip)) {
                return $ip;
            }
        }

        // 2. Cek cache
        $cached = cache('server_lan_ip');
        if ($cached && filter_var($cached, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->isLanIp($cached)) {
            return $cached;
        }

        // 3. Deteksi via UDP socket ke IP publik (tidak mengirim paket data)
        try {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock) {
                if (@socket_connect($sock, '8.8.8.8', 53)) {
                    @socket_getsockname($sock, $sockIp);
                    @socket_close($sock);
                    if (! empty($sockIp) && filter_var($sockIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->isLanIp($sockIp)) {
                        cache(['server_lan_ip' => $sockIp], 300);
                        return $sockIp;
                    }
                }
            }
        } catch (\Throwable $t) {
        }

        // 4. Deteksi di Windows lewat PowerShell
        if (DIRECTORY_SEPARATOR === '\\') {
            try {
                $output = @shell_exec('powershell -NoProfile -Command "(Get-NetRoute -DestinationPrefix \'0.0.0.0/0\' -ErrorAction SilentlyContinue | Sort-Object RouteMetric | Select-Object -First 1 | Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object IPAddress -notmatch \'169\.254\').IPAddress"');
                if (! empty($output)) {
                    $lines = explode("\n", trim($output));
                    foreach ($lines as $line) {
                        $candidate = trim($line);
                        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->isLanIp($candidate)) {
                            cache(['server_lan_ip' => $candidate], 300);
                            return $candidate;
                        }
                    }
                }
            } catch (\Throwable $t) {
            }
        }

        return null;
    }
}
