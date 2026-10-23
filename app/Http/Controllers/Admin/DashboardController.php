<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoSession;
use App\Models\Template;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_sesi'     => PhotoSession::count(),
            'sesi_hari_ini'  => PhotoSession::whereDate('created_at', today())->count(),
            'total_foto'     => (int) PhotoSession::pluck('raw_photos')->sum(fn ($p) => is_array($p) ? count($p) : 0),
            'template_aktif' => Template::active()->count(),
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
