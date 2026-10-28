<?php

namespace App\Http\Controllers;

use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Template;
use Illuminate\Http\Request;

class PhotoboothController extends Controller
{
    /** Layar sambutan. */
    public function index()
    {
        $setting = QueueSetting::forToday();

        $activeTicket = QueueTicket::today()
            ->whereIn('status', ['in_session', 'called'])
            ->orderByRaw("CASE WHEN status = 'in_session' THEN 1 WHEN status = 'called' THEN 2 ELSE 3 END")
            ->orderBy('queue_number')
            ->first();

        $waitingCount = QueueTicket::today()->waiting()->count();
        $totalToday = QueueTicket::today()->count();

        return view('welcome', compact('setting', 'activeTicket', 'waitingCount', 'totalToday'));
    }

    /** Galeri template: hanya template aktif; filter & pencarian berjalan di browser. */
    public function setup(Request $request)
    {
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
}
