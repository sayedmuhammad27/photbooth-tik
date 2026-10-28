<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QueueController extends Controller
{
    /**
     * Dashboard Manajemen Antrean Admin.
     */
    public function index(Request $request): View
    {
        $setting = QueueSetting::forToday();

        // Statistik antrean hari ini
        $stats = [
            'total'      => QueueTicket::today()->count(),
            'waiting'    => QueueTicket::today()->waiting()->count(),
            'in_session' => QueueTicket::today()->inSession()->count(),
            'called'     => QueueTicket::today()->called()->count(),
            'completed'  => QueueTicket::today()->where('status', 'completed')->count(),
            'skipped'    => QueueTicket::today()->where('status', 'skipped')->count(),
        ];

        // Tiket yang sedang dalam sesi foto atau sedang dipanggil
        $currentTicket = QueueTicket::today()
            ->whereIn('status', ['in_session', 'called'])
            ->orderByRaw("CASE WHEN status = 'in_session' THEN 1 WHEN status = 'called' THEN 2 ELSE 3 END")
            ->orderBy('queue_number')
            ->first();

        // Filter status di tabel
        $statusFilter = $request->query('status');
        $query = QueueTicket::today()->with(['template', 'photoSession']);

        if ($statusFilter && in_array($statusFilter, ['waiting', 'called', 'in_session', 'completed', 'skipped'], true)) {
            $query->where('status', $statusFilter);
        }

        $tickets = $query->orderByRaw("
            CASE status
                WHEN 'in_session' THEN 1
                WHEN 'called' THEN 2
                WHEN 'waiting' THEN 3
                WHEN 'skipped' THEN 4
                WHEN 'completed' THEN 5
                ELSE 6
            END, queue_number ASC
        ")->paginate(20)->withQueryString();

        $templates = Template::active()->ordered()->get();

        return view('admin.queue.index', compact(
            'setting',
            'stats',
            'currentTicket',
            'tickets',
            'templates',
            'statusFilter'
        ));
    }

    /**
     * Buka / tutup antrean.
     */
    public function toggleStatus(Request $request): RedirectResponse|JsonResponse
    {
        $setting = QueueSetting::forToday();
        $setting->update([
            'is_open' => ! $setting->is_open,
        ]);

        $msg = $setting->is_open
            ? 'Pendaftaran antrean BERHASIL DIBUKA!'
            : 'Pendaftaran antrean DITUTUP sementara.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'is_open' => (bool) $setting->is_open]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Panggil antrean berikutnya (Next in line).
     */
    public function callNext(Request $request): RedirectResponse|JsonResponse
    {
        $setting = QueueSetting::forToday();

        $nextTicket = QueueTicket::today()
            ->waiting()
            ->orderBy('queue_number')
            ->first();

        if (! $nextTicket) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Tidak ada antrean yang sedang menunggu.'], 422);
            }
            return back()->with('error', 'Tidak ada antrean yang sedang menunggu.');
        }

        $nextTicket->markCalled();
        $setting->update(['current_serving' => $nextTicket->queue_number]);

        $msg = "Antrean #{$nextTicket->queue_number} ({$nextTicket->name}) berhasil dipanggil!";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'ticket' => $nextTicket]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Admin menentukan pada antrian ke berapa secara spesifik:
     * Bisa memilih untuk "Panggil" atau "Langsung Mulai Sesi Foto".
     */
    public function setSpecific(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'target_number' => ['required', 'integer', 'min:1'],
            'action'        => ['required', 'in:call,start_session'],
            'template_id'   => ['nullable', 'exists:templates,id'],
        ]);

        $targetNum = (int) $request->target_number;
        $action = $request->action;

        $ticket = QueueTicket::today()->where('queue_number', $targetNum)->first();

        if (! $ticket) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => "Nomor antrean #{$targetNum} tidak ditemukan untuk hari ini."], 404);
            }
            return back()->with('error', "Nomor antrean #{$targetNum} tidak ditemukan untuk hari ini.");
        }

        $setting = QueueSetting::forToday();
        $setting->update(['current_serving' => $ticket->queue_number]);

        if ($request->filled('template_id')) {
            $ticket->template_id = $request->template_id;
        }

        if ($action === 'start_session') {
            // Selesaikan tiket lain yang sebelumnya in_session jika ada
            QueueTicket::today()->inSession()->where('id', '!=', $ticket->id)->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $ticket->startSession();
            $msg = "Antrean #{$ticket->queue_number} ({$ticket->name}) MASUK KE SESI FOTO! Layar awal photobooth sekarang menampilkan tombol mulai foto.";
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg, 'ticket' => $ticket]);
            }
            return back()->with('success', $msg);
        }

        $ticket->markCalled();
        $msg = "Antrean #{$ticket->queue_number} ({$ticket->name}) dipanggil!";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'ticket' => $ticket]);
        }
        return back()->with('success', $msg);
    }

    /**
     * Admin langsung memutuskan kapan dimulai sesi foto untuk antrean tertentu.
     */
    public function startSession(Request $request, QueueTicket $ticket): RedirectResponse|JsonResponse
    {
        // Selesaikan sesi foto lain yang masih aktif jika ada
        QueueTicket::today()->inSession()->where('id', '!=', $ticket->id)->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        if ($request->filled('template_id')) {
            $ticket->template_id = $request->template_id;
        }

        $ticket->startSession();

        $setting = QueueSetting::forToday();
        $setting->update(['current_serving' => $ticket->queue_number]);

        $msg = "Sesi foto untuk antrean #{$ticket->queue_number} ({$ticket->name}) DIMULAI! Pengunjung bisa langsung berfoto di booth.";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'ticket' => $ticket]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Selesaikan sesi antrean.
     */
    public function complete(Request $request, QueueTicket $ticket): RedirectResponse|JsonResponse
    {
        $ticket->markCompleted();

        $msg = "Antrean #{$ticket->queue_number} selesai.";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'ticket' => $ticket]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Lewati antrean (Skip).
     */
    public function skip(Request $request, QueueTicket $ticket): RedirectResponse|JsonResponse
    {
        $ticket->markSkipped();

        $msg = "Antrean #{$ticket->queue_number} dilewati.";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'ticket' => $ticket]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Kembalikan status antrean ke Menunggu (Reset).
     */
    public function resetTicket(Request $request, QueueTicket $ticket): RedirectResponse|JsonResponse
    {
        $ticket->resetToWaiting();

        $msg = "Status antrean #{$ticket->queue_number} dikembalikan ke Menunggu.";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'ticket' => $ticket]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Tambah antrean secara manual oleh admin.
     */
    public function storeManual(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'name'        => ['nullable', 'string', 'max:60'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'auto_start'  => ['nullable', 'boolean'],
        ]);

        $setting = QueueSetting::forToday();

        $ticket = DB::transaction(function () use ($setting, $request) {
            $nextNum = $setting->nextNumber();

            $status = $request->boolean('auto_start') ? 'in_session' : 'waiting';

            $t = QueueTicket::create([
                'queue_number'       => $nextNum,
                'name'               => $request->name ? trim($request->name) : 'Pengunjung #' . $nextNum,
                'status'             => $status,
                'template_id'        => $request->template_id ?: null,
                'session_started_at' => $request->boolean('auto_start') ? now() : null,
            ]);

            if ($request->boolean('auto_start')) {
                // Selesaikan sesi foto lain yang aktif
                QueueTicket::today()->inSession()->where('id', '!=', $t->id)->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
                $setting->update(['current_serving' => $nextNum]);
            }

            return $t;
        });

        $msg = $request->boolean('auto_start')
            ? "Antrean #{$ticket->queue_number} dibuat & LANGSUNG MASUK SESI FOTO!"
            : "Antrean #{$ticket->queue_number} ({$ticket->name}) berhasil ditambahkan ke daftar tunggu.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'ticket' => $ticket]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Reset antrean hari ini (hapus tiket & kembalikan nomor ke 0).
     */
    public function resetDay(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'in:RESET'],
        ], [
            'confirm.in' => 'Ketik kata "RESET" untuk mengonfirmasi penghapusan antrean hari ini.',
        ]);

        DB::transaction(function () {
            QueueTicket::today()->delete();
            $setting = QueueSetting::forToday();
            $setting->update([
                'last_number'     => 0,
                'current_serving' => 0,
            ]);
        });

        return back()->with('success', 'Semua antrean hari ini berhasil direset ke nomor 0.');
    }

    /**
     * API data untuk auto-refresh tabel admin.
     */
    public function liveData(Request $request): JsonResponse
    {
        $setting = QueueSetting::forToday();

        // 1 query cepat dengan GROUP BY untuk menggantikan 6 query terpisah
        $counts = QueueTicket::today()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $stats = [
            'total'      => (int) $counts->sum(),
            'waiting'    => (int) ($counts['waiting'] ?? 0),
            'in_session' => (int) ($counts['in_session'] ?? 0),
            'called'     => (int) ($counts['called'] ?? 0),
            'completed'  => (int) ($counts['completed'] ?? 0),
            'skipped'    => (int) ($counts['skipped'] ?? 0),
        ];

        $currentTicket = QueueTicket::today()
            ->whereIn('status', ['in_session', 'called'])
            ->orderByRaw("CASE WHEN status = 'in_session' THEN 1 WHEN status = 'called' THEN 2 ELSE 3 END")
            ->orderBy('queue_number')
            ->first();

        $latestTicket = QueueTicket::today()->latest('id')->first();

        $stateHash = md5(
            json_encode($stats) .
            ($currentTicket?->id ?? '') .
            ($currentTicket?->status ?? '') .
            $setting->current_serving .
            $setting->is_open .
            ($latestTicket?->id ?? '')
        );

        // Jika data tidak berubah, langsung balas tanpa query tabel dan render view
        if ($request->filled('hash') && $request->query('hash') === $stateHash) {
            return response()->json([
                'unchanged'  => true,
                'state_hash' => $stateHash,
            ]);
        }

        // Filter status di tabel
        $statusFilter = $request->query('status');
        $query = QueueTicket::today()->with(['template', 'photoSession']);

        if ($statusFilter && in_array($statusFilter, ['waiting', 'called', 'in_session', 'completed', 'skipped'], true)) {
            $query->where('status', $statusFilter);
        }

        $tickets = $query->orderByRaw("
            CASE status
                WHEN 'in_session' THEN 1
                WHEN 'called' THEN 2
                WHEN 'waiting' THEN 3
                WHEN 'skipped' THEN 4
                WHEN 'completed' THEN 5
                ELSE 6
            END, queue_number ASC
        ")->paginate(20)->withQueryString();

        $htmlRows = view('admin.queue._rows', compact('tickets'))->render();
        $htmlActive = view('admin.queue._active_card', compact('currentTicket', 'setting', 'stats'))->render();
        $htmlPagination = $tickets->links('vendor.pagination.pixel')->toHtml();

        return response()->json([
            'unchanged'       => false,
            'is_open'         => (bool) $setting->is_open,
            'current_serving' => (int) $setting->current_serving,
            'stats'           => $stats,
            'current_ticket'  => $currentTicket ? [
                'id'           => $currentTicket->id,
                'queue_number' => $currentTicket->queue_number,
                'name'         => $currentTicket->name,
                'status'       => $currentTicket->status,
                'status_label' => $currentTicket->status_label,
            ] : null,
            'html_rows'       => $htmlRows,
            'html_active'     => $htmlActive,
            'html_pagination' => $htmlPagination,
            'state_hash'      => $stateHash,
            'last_ticket_id'  => $latestTicket?->id ?: 0,
            'latest_ticket'   => $latestTicket ? [
                'id'           => $latestTicket->id,
                'queue_number' => $latestTicket->queue_number,
                'name'         => $latestTicket->name,
            ] : null,
        ]);
    }
}
