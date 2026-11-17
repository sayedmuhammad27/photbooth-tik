# Menjalankan photobooth dengan beban lebih ringan

Laravel 12, JavaScript browser, GD/Intervention, dan digiCamControl tetap digunakan.
Tidak ada migrasi database atau perubahan ukuran hasil cetak (1200 × 1800, JPEG kualitas 92).
Tampilan, stylesheet, font, dan halaman awal launcher tetap mengikuti versi sebelum optimasi (`b10057f`). Launcher membuka Kamera Hub Admin seperti semula.

## Pemakaian

1. Selesaikan instalasi Laravel dan database seperti di README-PHOTOBOOTH.md.
2. Untuk DSLR, atur `DIGICAM_ENABLED=true` di `.env`, lalu `php artisan config:clear`.
3. Jalankan `jalankan-lokal.bat`, atau `jalankan-online.bat` untuk QR publik melalui Cloudflare.
4. Kedua launcher menggunakan Apache XAMPP khusus proyek pada port **8000**. Konfigurasi dan log berada di `storage/app/runtime`; konfigurasi XAMPP global tidak diubah.
5. Jika port 8000 masih dipakai `php artisan serve` lama, hentikan server lama dahulu. Launcher sengaja tidak mematikan proses lain untuk mengambil port tersebut.

**Satu laptop + monitor tambahan:** buka `http://localhost:8000`, pilih sumber **Canon (digiCamControl)**. Kamera Hub tidak diperlukan. Pemilihan sumber sebelumnya tetap diingat; ubah dropdown jika sebelumnya memakai Kamera Admin.

**Bilik di tablet/HP atau komputer lain:** admin membuka `/admin/camera-hub` dan memulai siaran; bilik memilih **Kamera Admin (Bilik)**. WebRTC diprioritaskan, HTTP tetap tersedia jika koneksi WebRTC gagal. Pengunjung tetap dapat mengambil hasil melalui QR lokal atau tunnel.

**Webcam:** pilih Webcam Perangkat Ini; untuk webcam yang berada di komputer admin, gunakan Kamera Hub. Webcam browser memerlukan localhost atau HTTPS.

Gunakan `scripts/stop-booth-server.ps1` untuk menghentikan hanya Apache photobooth. `matikan-semua.bat` tetap menghentikan layanan XAMPP, kamera, dan tunnel seperti sebelumnya. Jalankan PHP melalui Apache pada acara; `php artisan serve` cukup untuk pengembangan.

## Pengurangan pekerjaan

- HTTP preview dikirim hanya selama ada bilik yang meminta frame. Ketika semua bilik memakai WebRTC, lease HTTP berakhir dalam sekitar 4–5 detik dan pembuatan/upload JPEG berhenti. Bilik HTTP dan WebRTC tetap dapat aktif bersamaan.
- Kontrol jepret tetap tersedia melalui heartbeat setiap detik tanpa upload JPEG. Preview DSLR digambar ke canvas sekali per frame baru untuk WebRTC. Kualitas preview dibatasi terpisah dari resolusi foto.
- Polling dijeda saat jepret, upload hasil, dan saat bilik tersembunyi. Kamera Hub tetap melayani bilik ketika tab admin berada di belakang.
- Polling snapshot dihentikan ketika notifikasi foto siap diterima, agar foto tidak diunduh dua kali. Timeout dan retry tetap tersedia.
- Slot dengan foto dan ukuran sama menggunakan ulang hasil crop. Urutan tumpukan slot dan overlay template tetap dipertahankan.
- Endpoint relay tidak lagi membaca/menulis session setiap frame. Lock file melindungi pembacaan preview/snapshot ketika request berjalan bersamaan.
- Polling antrean tidak bertumpuk dan berhenti ketika tab tersembunyi.

## Verifikasi

Hasil verifikasi perubahan ini: **46 tes backend (166 assertions)** dan **6 skenario browser** lulus.
Benchmark sintetis 4 foto 1600 × 1015 pada 8 slot menghasilkan median **721,4 ms → 458,6 ms**
(9 putaran, sekitar **36,4%** lebih singkat). Ini mengukur komposisi dan encoding strip,
bukan waktu shutter, USB, atau cetak. Uji Apache menjalankan dua request PHP masing-masing
700 ms secara bersamaan dalam **705 ms**, dengan cURL dan GD aktif.

Backend menggunakan SQLite in-memory (`phpunit.xml`), tidak mengubah database acara:

```powershell
$env:APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
php vendor/phpunit/phpunit/phpunit
```

Browser memakai halaman Blade asli, kamera palsu, dan endpoint digiCamControl tiruan:

```powershell
npm ci
npx playwright install chromium
npm run test:browser
```

Jika Microsoft Edge sudah terpasang, `$env:PLAYWRIGHT_CHANNEL = 'msedge'` dapat dipakai tanpa mengunduh Chromium. Dependensi Playwright hanya untuk pengujian, tidak dimuat oleh aplikasi photobooth.

Uji Apache pada port terpisah (membuat dan menghapus endpoint uji sementara, lalu menghentikan instance uji):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/check-booth-server.ps1 -Port 8018
```

Perbandingan komposisi strip lama dan baru dengan input sama:

```powershell
php scripts/benchmark-strip.php
```

Uji otomatis tidak mengukur waktu fokus/shutter DSLR, transfer USB asli, driver printer, atau kondisi Wi-Fi acara. Lakukan satu sesi lengkap, retake, unduh QR, dan cetak dengan perangkat acara sebelum digunakan pengunjung.
