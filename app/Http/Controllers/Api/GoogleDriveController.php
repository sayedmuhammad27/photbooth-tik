<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotoSession;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GoogleDriveController extends Controller
{
    public function __construct(
        protected GoogleDriveService $driveService
    ) {}

    /**
     * API: Unggah hasil foto suatu sesi ke Google Drive.
     * POST /api/sessions/{token}/upload-drive
     */
    public function uploadSession(Request $request, string $token): JsonResponse
    {
        $session = PhotoSession::where('session_token', $token)->first();

        if (! $session) {
            return response()->json([
                'ok'      => false,
                'message' => 'Sesi foto tidak ditemukan.',
            ], 404);
        }

        // Jika sudah pernah disinkronkan dan tidak dipaksa ulang
        if ($session->isSyncedToGoogleDrive() && ! $request->boolean('force', false)) {
            return response()->json([
                'ok'               => true,
                'already_synced'   => true,
                'message'          => 'Foto sudah tersimpan di Google Drive.',
                'google_drive_url' => $session->google_drive_url,
                'file_id'          => $session->google_drive_file_id,
            ]);
        }

        $includeRaw = $request->boolean('include_raw', false);
        $result = $this->driveService->uploadSession($session, $includeRaw);

        $status = $result['ok'] ? 200 : 502;
        return response()->json($result, $status);
    }

    /**
     * API: Periksa status Google Drive untuk suatu sesi foto.
     * GET /api/sessions/{token}/drive
     */
    public function getSessionStatus(string $token): JsonResponse
    {
        $session = PhotoSession::where('session_token', $token)->first();

        if (! $session) {
            return response()->json([
                'ok'      => false,
                'message' => 'Sesi foto tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'ok'               => true,
            'synced'           => $session->isSyncedToGoogleDrive(),
            'status'           => $session->google_drive_status ?: 'pending',
            'google_drive_url' => $session->google_drive_url,
            'file_id'          => $session->google_drive_file_id,
            'synced_at'        => $session->google_drive_synced_at?->toIso8601String(),
        ]);
    }

    /**
     * API: Unggah file foto langsung ke Google Drive secara mandiri.
     * POST /api/google-drive/upload
     */
    public function directUpload(Request $request): JsonResponse
    {
        $request->validate([
            'photo'        => ['nullable', 'file', 'image', 'max:15360'],
            'photo_base64' => ['nullable', 'string'],
            'name'         => ['nullable', 'string', 'max:120'],
        ]);

        $fileName = $request->input('name') ?: ('photobooth_' . date('Ymd_His') . '.jpg');
        $binary = null;
        $mime = 'image/jpeg';

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $binary = file_get_contents($file->getRealPath());
            $mime = $file->getMimeType() ?: 'image/jpeg';
            if (! $request->input('name')) {
                $fileName = $file->getClientOriginalName() ?: $fileName;
            }
        } elseif ($request->input('photo_base64')) {
            $raw = $request->input('photo_base64');
            $parts = explode(',', $raw, 2);
            $binary = base64_decode($parts[1] ?? $parts[0]);
        }

        if (! $binary || strlen($binary) < 50) {
            return response()->json([
                'ok'      => false,
                'message' => 'Berkas foto tidak valid atau kosong.',
            ], 422);
        }

        $uploaded = $this->driveService->uploadContent($binary, $fileName, $mime);

        if (! $uploaded) {
            return response()->json([
                'ok'      => false,
                'message' => 'Gagal mengunggah foto ke Google Drive. Periksa kredensial di file .env.',
            ], 502);
        }

        return response()->json([
            'ok'               => true,
            'message'          => 'Foto berhasil diunggah ke Google Drive.',
            'google_drive_url' => $uploaded['web_view_link'],
            'file_id'          => $uploaded['id'],
            'name'             => $uploaded['name'],
        ]);
    }

    /**
     * API: Status dan pengujian koneksi Google Drive.
     * GET /api/google-drive/status
     */
    public function status(): JsonResponse
    {
        $test = $this->driveService->testConnection();
        return response()->json($test, $test['ok'] ? 200 : 503);
    }
}
