<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoSession;
use App\Models\QueueTicket;
use App\Models\Template;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $start = today()->startOfDay()->format('Y-m-d H:i:s');
        $end = today()->addDay()->startOfDay()->format('Y-m-d H:i:s');

        $sessionCounts = DB::table('photo_sessions')
            ->selectRaw('COUNT(*) as total_sesi, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as sesi_hari_ini', [$start, $end])
            ->first();

        $totalFoto = DB::table('photo_sessions')
            ->select('raw_photos')
            ->cursor()
            ->sum(function ($row) {
                $decoded = is_string($row->raw_photos) ? json_decode($row->raw_photos, true) : $row->raw_photos;

                return is_array($decoded) ? count($decoded) : 0;
            });

        $queueCounts = QueueTicket::today()
            ->selectRaw("COUNT(*) as total_count, SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting_count")
            ->first();

        $stats = [
            'total_sesi'       => (int) ($sessionCounts?->total_sesi ?? 0),
            'sesi_hari_ini'    => (int) ($sessionCounts?->sesi_hari_ini ?? 0),
            'total_foto'       => (int) $totalFoto,
            'template_aktif'   => Template::active()->count(),
            'antrean_hari_ini' => (int) ($queueCounts?->total_count ?? 0),
            'antrean_menunggu' => (int) ($queueCounts?->waiting_count ?? 0),
            'antrean_aktif'    => QueueTicket::today()->whereIn('status', ['in_session', 'called'])->first(),
        ];

        return view('admin.dashboard', [
            'stats'   => $stats,
            'recent'  => PhotoSession::with('template')->latest()->limit(6)->get(),
            'popular' => Template::withCount('photoSessions')->orderByDesc('photo_sessions_count')->orderBy('name')->limit(5)->get(),
        ]);
    }

    public function sessions()
    {
        return view('admin.sessions', [
            'sessions' => PhotoSession::with('template')->latest()->paginate(12),
        ]);
    }
}
