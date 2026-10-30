<?php

namespace App\Services;

use App\Models\PhotoSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GoogleDriveService
{
    protected array $config;

    public function __construct()
    {
        $this->config = (array) config('photobooth.google_drive', []);
    }

    /**
     * Periksa apakah konfigurasi Google Drive sudah diisi di .env
     */
    public function isConfigured(): bool
    {
        if (! empty($this->config['webhook_url'])) {
            return true;
        }

        if (! empty($this->config['client_id']) && ! empty($this->config['client_secret']) && ! empty($this->config['refresh_token'])) {
            return true;
        }

        if (! empty($this->config['service_account_json'])) {
            return true;
        }

        return false;
    }

    /**
     * Periksa apakah integrasi Google Drive aktif dan siap dipakai.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && $this->isConfigured();
    }

    /**
     * Dapatkan access token Google API (via OAuth2 Refresh Token atau Service Account).
     */
    public function getAccessToken(): ?string
    {
        $cached = Cache::get('gdrive_access_token');
        if ($cached) {
            return $cached;
        }

        // 1. Coba lewat OAuth2 Refresh Token
        if (! empty($this->config['client_id']) && ! empty($this->config['client_secret']) && ! empty($this->config['refresh_token'])) {
            try {
                $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                    'client_id'     => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $this->config['refresh_token'],
                    'grant_type'    => 'refresh_token',
                ]);

                if ($response->successful()) {
                    $token = $response->json('access_token');
                    $expiresIn = (int) $response->json('expires_in', 3600);
                    if ($token) {
                        Cache::put('gdrive_access_token', $token, now()->addSeconds(max(60, $expiresIn - 120)));
                        return $token;
                    }
                }

                Log::error('Google Drive OAuth2 refresh token gagal', ['response' => $response->json()]);
            } catch (Throwable $e) {
                Log::error('Google Drive OAuth2 exception', ['error' => $e->getMessage()]);
            }
        }

        // 2. Coba lewat Service Account JSON
        if (! empty($this->config['service_account_json'])) {
            $token = $this->getServiceAccountAccessToken();
            if ($token) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Dapatkan token lewat Google Cloud Service Account JWT.
     */
    protected function getServiceAccountAccessToken(): ?string
    {
        try {
            $jsonInput = trim($this->config['service_account_json']);
            $sa = null;

            if (str_starts_with($jsonInput, '{')) {
                $sa = json_decode($jsonInput, true);
            } elseif (file_exists($jsonInput)) {
                $sa = json_decode(file_get_contents($jsonInput), true);
            } elseif (file_exists(base_path($jsonInput))) {
                $sa = json_decode(file_get_contents(base_path($jsonInput)), true);
            }

            if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
                Log::error('Format Service Account Google Drive tidak valid.');
                return null;
            }

            $now = time();
            $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $claim = rtrim(strtr(base64_encode(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive',
                'aud'   => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'exp'   => $now + 3600,
                'iat'   => $now,
            ])), '+/', '-_'), '=');

            $data = $header . '.' . $claim;
            $signature = '';

            $privateKey = openssl_pkey_get_private($sa['private_key']);
            if (! $privateKey) {
                Log::error('Private key Service Account tidak dapat dimuat.');
                return null;
            }

            openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $jwt = $data . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $res = Http::asForm()->timeout(15)->post($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($res->successful() && $res->json('access_token')) {
                $token = $res->json('access_token');
                $expiresIn = (int) $res->json('expires_in', 3600);
                Cache::put('gdrive_access_token', $token, now()->addSeconds(max(60, $expiresIn - 120)));
                return $token;
            }

            Log::error('Service Account token error', ['response' => $res->json()]);
        } catch (Throwable $e) {
            Log::error('Service Account exception', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Unggah data biner foto ke Google Drive.
     *
     * @return array{id: string, name: string, web_view_link: string, web_content_link: ?string}|null
     */
    public function uploadContent(string $binaryContent, string $fileName, string $mimeType = 'image/jpeg', ?string $folderId = null): ?array
    {
        $targetFolder = $folderId ?: ($this->config['folder_id'] ?: null);

        // 1. Opsi Webhook / Google Apps Script
        if (! empty($this->config['webhook_url'])) {
            try {
                $res = Http::timeout(45)->post($this->config['webhook_url'], [
                    'name'     => $fileName,
                    'mimeType' => $mimeType,
                    'folderId' => $targetFolder,
                    'data'     => base64_encode($binaryContent),
                ]);

                if ($res->successful()) {
                    $json = $res->json();
                    $fileId = $json['fileId'] ?? $json['id'] ?? null;
                    $url = $json['url'] ?? $json['webViewLink'] ?? ($fileId ? "https://drive.google.com/file/d/{$fileId}/view" : null);

                    if ($url) {
                        return [
                            'id'               => (string) ($fileId ?: 'gdrive_' . md5($fileName)),
                            'name'             => $fileName,
                            'web_view_link'    => $url,
                            'web_content_link' => $json['downloadUrl'] ?? null,
                        ];
                    }
                }

                Log::error('Google Drive Apps Script Webhook error', ['response' => $res->body()]);
            } catch (Throwable $e) {
                Log::error('Google Drive Webhook exception', ['error' => $e->getMessage()]);
            }
        }

        // 2. Opsi Google Drive REST API v3
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            Log::warning('Google Drive: tidak ada access token yang valid.');
            return null;
        }

        try {
            $metadata = ['name' => $fileName];
            if ($targetFolder) {
                $metadata['parents'] = [$targetFolder];
            }

            $boundary = '-------PhotoboothTIK' . md5(uniqid('', true));
            $delimiter = "\r\n--" . $boundary . "\r\n";
            $closeDelim = "\r\n--" . $boundary . "--";

            $body = $delimiter
                . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
                . json_encode($metadata)
                . $delimiter
                . "Content-Type: {$mimeType}\r\n\r\n"
                . $binaryContent
                . $closeDelim;

            $uploadRes = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type'   => 'multipart/related; boundary=' . $boundary,
                    'Content-Length' => strlen($body),
                ])
                ->withBody($body, 'multipart/related; boundary=' . $boundary)
                ->timeout(45)
                ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink,webContentLink');

            if (! $uploadRes->successful()) {
                Log::error('Google Drive API upload failed', ['response' => $uploadRes->json()]);
                return null;
            }

            $fileData = $uploadRes->json();
            $fileId = $fileData['id'] ?? null;

            if (! $fileId) {
                return null;
            }

            // Buat berkas bisa dibaca / dibuka oleh siapa saja yang memiliki tautan (publik view)
            try {
                Http::withToken($accessToken)
                    ->timeout(10)
                    ->post("https://www.googleapis.com/drive/v3/files/{$fileId}/permissions", [
                        'role' => 'reader',
                        'type' => 'anyone',
                    ]);
            } catch (Throwable $permEx) {}

            $webViewLink = $fileData['webViewLink'] ?? "https://drive.google.com/file/d/{$fileId}/view";

            return [
                'id'               => $fileId,
                'name'             => $fileData['name'] ?? $fileName,
                'web_view_link'    => $webViewLink,
                'web_content_link' => $fileData['webContentLink'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('Google Drive upload exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Unggah berkas dari penyimpanan lokal ke Google Drive.
     */
    public function uploadFile(string $filePath, string $fileName, string $mimeType = 'image/jpeg', ?string $folderId = null): ?array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($filePath)) {
            Log::warning('Google Drive: berkas lokal tidak ditemukan', ['path' => $filePath]);
            return null;
        }

        $content = $disk->get($filePath);
        if ($content === null) {
            return null;
        }

        return $this->uploadContent($content, $fileName, $mimeType, $folderId);
    }

    /**
     * Unggah hasil sesi foto ke Google Drive dan simpan tautan ke model PhotoSession.
     */
    public function uploadSession(PhotoSession $session, bool $includeRaw = false): array
    {
        $sessionToken = $session->session_token;
        $finalPath = $session->final_image_path;

        $fileName = 'photobooth_' . date('Ymd_His') . '_' . substr($sessionToken, 0, 8) . '.jpg';

        $uploaded = $this->uploadFile($finalPath, $fileName, 'image/jpeg');

        if (! $uploaded) {
            $session->update([
                'google_drive_status' => 'failed',
            ]);

            return [
                'ok'      => false,
                'message' => 'Gagal mengunggah foto ke Google Drive. Periksa konfigurasi API Google Drive di .env',
            ];
        }

        $rawUrls = [];
        if ($includeRaw && is_array($session->raw_photos)) {
            foreach ($session->raw_photos as $i => $rawPath) {
                $rawName = 'photobooth_' . substr($sessionToken, 0, 8) . '_raw_' . ($i + 1) . '.jpg';
                $rawUp = $this->uploadFile($rawPath, $rawName, 'image/jpeg');
                if ($rawUp && ! empty($rawUp['web_view_link'])) {
                    $rawUrls[] = $rawUp['web_view_link'];
                }
            }
        }

        $session->update([
            'google_drive_file_id'   => $uploaded['id'],
            'google_drive_url'       => $uploaded['web_view_link'],
            'google_drive_raw_urls'  => $rawUrls,
            'google_drive_status'    => 'synced',
            'google_drive_synced_at' => now(),
        ]);

        return [
            'ok'               => true,
            'message'          => 'Foto berhasil diunggah ke Google Drive.',
            'google_drive_url' => $uploaded['web_view_link'],
            'file_id'          => $uploaded['id'],
            'raw_urls'         => $rawUrls,
        ];
    }

    /**
     * Uji koneksi ke Google Drive API.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok'         => false,
                'configured' => false,
                'message'    => 'Google Drive API belum dikonfigurasi di file .env. Masukkan GOOGLE_DRIVE_FOLDER_ID dan kredensial OAuth2/Service Account/Webhook.',
            ];
        }

        // Test mode Webhook
        if (! empty($this->config['webhook_url'])) {
            try {
                $res = Http::timeout(10)->post($this->config['webhook_url'], ['action' => 'ping']);
                return [
                    'ok'         => true,
                    'configured' => true,
                    'method'     => 'webhook',
                    'message'    => 'Terkoneksi ke Google Apps Script Webhook.',
                ];
            } catch (Throwable $e) {
                return [
                    'ok'         => false,
                    'configured' => true,
                    'method'     => 'webhook',
                    'message'    => 'Gagal menghubungi Webhook: ' . $e->getMessage(),
                ];
            }
        }

        // Test mode OAuth2 / Service Account
        $token = $this->getAccessToken();
        if (! $token) {
            return [
                'ok'         => false,
                'configured' => true,
                'method'     => ! empty($this->config['client_id']) ? 'oauth2' : 'service_account',
                'message'    => 'Gagal mengautentikasi ke Google API. Pastikan Client ID, Client Secret, Refresh Token atau Service Account JSON valid.',
            ];
        }

        $folderId = $this->config['folder_id'] ?: 'root';
        try {
            $folderRes = Http::withToken($token)->timeout(10)->get("https://www.googleapis.com/drive/v3/files/{$folderId}?fields=id,name,mimeType");

            if ($folderRes->successful()) {
                $data = $folderRes->json();
                return [
                    'ok'          => true,
                    'configured'  => true,
                    'method'      => ! empty($this->config['client_id']) ? 'oauth2' : 'service_account',
                    'folder_name' => $data['name'] ?? $folderId,
                    'folder_id'   => $data['id'] ?? $folderId,
                    'message'     => 'Koneksi Google Drive API aktif dan terverifikasi.',
                ];
            }

            return [
                'ok'         => false,
                'configured' => true,
                'method'     => 'api',
                'message'    => 'Kredensial valid, namun folder Google Drive tidak dapat diakses (HTTP ' . $folderRes->status() . '). Pastikan Folder ID benar dan sudah dibagikan.',
            ];
        } catch (Throwable $e) {
            return [
                'ok'         => false,
                'configured' => true,
                'message'    => 'Koneksi gagal: ' . $e->getMessage(),
            ];
        }
    }
}
