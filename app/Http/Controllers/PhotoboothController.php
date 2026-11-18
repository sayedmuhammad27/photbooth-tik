<?php

namespace App\Http\Controllers;

use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Template;
use Illuminate\Http\Request;

class PhotoboothController extends Controller
{
    /** Layar sambutan. */
    public function index(Request $request)
    {
        $this->capturePublicDomain($request);
        $setting = QueueSetting::forToday();

        $activeTicket = QueueTicket::today()
            ->whereIn('status', ['in_session', 'called'])
            ->orderByRaw("CASE WHEN status = 'in_session' THEN 1 WHEN status = 'called' THEN 2 ELSE 3 END")
            ->orderBy('queue_number')
            ->first();

        $queueCounts = QueueTicket::today()
            ->selectRaw("COUNT(*) as total_count, SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting_count")
            ->first();
        $waitingCount = (int) ($queueCounts?->waiting_count ?? 0);
        $totalToday = (int) ($queueCounts?->total_count ?? 0);

        $activeTemplates = Template::active()
            ->ordered()
            ->get(['id', 'name', 'description', 'frame_asset', 'slot_count']);
        $previewTemplates = $activeTemplates->take(3);
        $totalTemplates = $activeTemplates->count();

        return view('welcome', compact('setting', 'activeTicket', 'waitingCount', 'totalToday', 'previewTemplates', 'totalTemplates'));
    }

    /** Galeri template: hanya template aktif; filter & pencarian berjalan di browser. */
    public function setup(Request $request)
    {
        $this->capturePublicDomain($request);
        $queueNum = $request->input('queue') ?: session('active_queue_number');

        if (! app()->environment('testing') && ! auth()->check()) {
            if (! $queueNum) {
                return redirect()->route('home')
                    ->with('error', 'Sesi foto tidak bisa dimulai! Anda belum memiliki nomor antrean. Silakan ambil nomor antrean atau gunakan menu Test Foto.');
            }

            $ticket = QueueTicket::today()->where('queue_number', $queueNum)->first();
            if (! $ticket || $ticket->status !== 'in_session') {
                return redirect()->route('home')
                    ->with('error', 'Sesi foto tidak bisa dimulai! Nomor antrean #' . $queueNum . ' belum disetujui oleh admin untuk masuk sesi foto.');
            }
        }

        if ($queueNum) {
            session(['active_queue_number' => $queueNum]);
        }

        $templates = Template::active()
            ->ordered()
            ->get()
            ->map(fn (Template $t) => $t->toBoothArray())
            ->values();

        return view('photobooth.setup', ['templates' => $templates]);
    }

    private function capturePublicDomain(Request $request): void
    {
        $host = $request->getHost();
        if ($host && ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '[::1]'], true) && ! preg_match('/^(192\.168|10\.|172\.)/', $host)) {
            $scheme = (str_contains($host, 'trycloudflare.com') || str_contains($host, 'ngrok')) ? 'https' : $request->getScheme();
            session(['active_public_url' => "{$scheme}://{$host}"]);
        }
    }
}
