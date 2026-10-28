<?php

namespace App\Http\Controllers;

use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    /**
     * Mengambil nomor antrean baru (dari halaman awal / kiosk / HP).
     */
    public function take(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $setting = QueueSetting::forToday();

        // Cek apakah pendaftaran antrean dibuka
        if (! $setting->is_open && ! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Antrean saat ini sedang ditutup oleh admin. Silakan tunggu sampai antrean dibuka.',
            ], 422);
        }

        $ticket = DB::transaction(function () use ($setting, $validated) {
            $nextNum = $setting->nextNumber();

            return QueueTicket::create([
                'queue_number' => $nextNum,
                'name'         => ! empty($validated['name']) ? trim($validated['name']) : 'Pengunjung #' . $nextNum,
                'status'       => 'waiting',
            ]);
        });

        // Hitung berapa orang yang mengantre di depannya
        $aheadCount = QueueTicket::today()
            ->waiting()
            ->where('queue_number', '<', $ticket->queue_number)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Nomor antrean berhasil diambil!',
            'ticket'  => [
                'id'           => $ticket->id,
                'queue_number' => $ticket->queue_number,
                'name'         => $ticket->name,
                'status'       => $ticket->status,
                'status_label' => $ticket->status_label,
                'ahead_count'  => $aheadCount,
                'created_at'   => $ticket->created_at->format('H:i:s'),
                'date'         => $ticket->created_at->translatedFormat('d F Y'),
            ],
        ]);
    }

    /**
     * Endpoint status publik untuk real-time update di layar awal (Welcome / Kiosk).
     */
    public function status(): JsonResponse
    {
        $setting = QueueSetting::forToday();

        // Cari tiket yang sedang aktif masuk ke sesi foto atau sedang dipanggil
        $activeTicket = QueueTicket::today()
            ->whereIn('status', ['in_session', 'called'])
            ->orderByRaw("CASE WHEN status = 'in_session' THEN 1 WHEN status = 'called' THEN 2 ELSE 3 END")
            ->orderBy('queue_number')
            ->first();

        // Hitung total dan sisa yang menunggu
        $waitingCount = QueueTicket::today()->waiting()->count();
        $totalToday = QueueTicket::today()->count();

        // Link langsung mulai foto untuk antrean aktif
        $sessionUrl = null;
        if ($activeTicket && $activeTicket->status === 'in_session') {
            // Jika admin sudah menentukan template tertentu
            if ($activeTicket->template_id && $activeTicket->template && $activeTicket->template->is_active) {
                $sessionUrl = route('photobooth.session', [
                    'template' => $activeTicket->template_id,
                    'queue'    => $activeTicket->queue_number,
                ]);
            } else {
                // Pengunjung bebas memilih template di galeri
                $sessionUrl = route('photobooth.setup', [
                    'queue' => $activeTicket->queue_number,
                ]);
            }
        }

        return response()->json([
            'is_open'         => (bool) $setting->is_open,
            'current_serving' => (int) $setting->current_serving,
            'waiting_count'   => $waitingCount,
            'total_today'     => $totalToday,
            'active_ticket'   => $activeTicket ? [
                'id'           => $activeTicket->id,
                'queue_number' => $activeTicket->queue_number,
                'name'         => $activeTicket->name,
                'status'       => $activeTicket->status,
                'status_label' => $activeTicket->status_label,
                'template_name'=> $activeTicket->template ? $activeTicket->template->name : null,
                'session_url'  => $sessionUrl,
            ] : null,
        ]);
    }

    /**
     * Cek status tiket pribadi (misal dari kartu antrean pengguna).
     */
    public function check(int $queueNumber): JsonResponse
    {
        $ticket = QueueTicket::today()
            ->where('queue_number', $queueNumber)
            ->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor antrean tidak ditemukan untuk hari ini.',
            ], 404);
        }

        $aheadCount = QueueTicket::today()
            ->waiting()
            ->where('queue_number', '<', $ticket->queue_number)
            ->count();

        return response()->json([
            'success' => true,
            'ticket'  => [
                'id'           => $ticket->id,
                'queue_number' => $ticket->queue_number,
                'name'         => $ticket->name,
                'status'       => $ticket->status,
                'status_label' => $ticket->status_label,
                'ahead_count'  => $aheadCount,
            ],
        ]);
    }

    /**
     * Verifikasi nomor antrean sebelum memulai sesi foto.
     * Sesi foto TIDAK BISA dimulai jika admin belum menyetujui (in_session).
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'queue_number' => ['required', 'integer', 'min:1'],
        ]);

        $ticket = QueueTicket::today()
            ->where('queue_number', $request->queue_number)
            ->first();

        if (! $ticket) {
            return response()->json([
                'valid'   => false,
                'message' => "Nomor antrean #{$request->queue_number} tidak ditemukan untuk hari ini. Silakan ambil nomor antrean terlebih dahulu.",
            ], 404);
        }

        if ($ticket->status !== 'in_session') {
            $statusMsg = match ($ticket->status) {
                'waiting'   => "Antrean #{$ticket->queue_number} masih dalam daftar tunggu dan BELUM DISETUJUI ADMIN untuk masuk bilik foto. Silakan tunggu giliran Anda dipanggil.",
                'called'    => "Antrean #{$ticket->queue_number} sedang dipanggil, tetapi admin belum menekan tombol 'Mulai Sesi Foto'. Silakan konfirmasi ke meja admin.",
                'completed' => "Antrean #{$ticket->queue_number} sudah menyelesaikan sesi foto sebelumnya.",
                'skipped'   => "Antrean #{$ticket->queue_number} telah dilewati oleh admin. Silakan konfirmasi ke meja admin.",
                default     => "Antrean #{$ticket->queue_number} belum disetujui admin untuk sesi foto.",
            };

            return response()->json([
                'valid'        => false,
                'status'       => $ticket->status,
                'status_label' => $ticket->status_label,
                'message'      => $statusMsg,
            ], 422);
        }

        // Disetujui admin!
        session(['active_queue_number' => $ticket->queue_number]);

        $redirectUrl = route('photobooth.setup', ['queue' => $ticket->queue_number]);
        if ($ticket->template_id && $ticket->template && $ticket->template->is_active) {
            $redirectUrl = route('photobooth.session', ['template' => $ticket->template_id, 'queue' => $ticket->queue_number]);
        }

        return response()->json([
            'valid'        => true,
            'ticket'       => [
                'queue_number' => $ticket->queue_number,
                'name'         => $ticket->name,
                'status'       => $ticket->status,
                'status_label' => $ticket->status_label,
            ],
            'redirect_url' => $redirectUrl,
            'message'      => "Antrean #{$ticket->queue_number} ({$ticket->name}) SUDAH DISETUJUI ADMIN! Mengarahkan ke bilik foto...",
        ]);
    }
}
