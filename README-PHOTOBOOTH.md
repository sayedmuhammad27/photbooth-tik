# Photobooth TIK — Expo Wisuda Jurusan TIK 2026

Aplikasi photobooth berbasis Laravel 12: pilih template → izinkan kamera → hitung mundur → foto →
tinjau/ulangi → strip disusun di server (4R, 1200×1800) → unduh atau ambil lewat QR code.
Tampilan mengikuti poster "Welcome to Expo Wisuda Jurusan TIK 2026" (pixel-art kota malam).

> **Status jujur:** PHP/Composer tidak tersedia di lingkungan tempat kode ini dikerjakan, jadi
> seluruh kode PHP **belum dijalankan** (lihat bagian *Status pengujian*). Frontend (galeri, kamera,
> countdown, retake, upload) sudah diuji di Chromium. Jalankan langkah verifikasi di bagian 4 sebelum event.

---

## 1. Instalasi di XAMPP (Windows)

Prasyarat: PHP 8.2+ dengan ekstensi `gd`, `fileinfo`, `pdo_mysql` (dan `pdo_sqlite` bila ingin menjalankan tes),
Composer, MySQL dari XAMPP.

1. **Salin file.** Ekstrak ZIP ke `D:\Xampp\htdocs\photbooth-tik-v2` (timpa file lama; folder `vendor`,
   `.env`, dan foto hasil lama di `storage\app\public\results` tidak ikut ZIP sehingga tidak tertimpa).
   Hapus folder sisa lama bila masih ada: `public\build` dan file `public\hot`.
2. **Dependensi** (hanya bila `vendor` belum ada):
   ```bat
   cd D:\Xampp\htdocs\photbooth-tik-v2
   composer install
   ```
3. **`.env`**: bila belum ada, `copy .env.example .env` lalu `php artisan key:generate`.
   Bila sudah ada, **jangan ditimpa**; cukup tambahkan variabel baru di bawah (bagian 2).
4. **Database:** nyalakan MySQL di XAMPP, buat database kosong `photobooth_tik_v2` (atau sesuai `DB_DATABASE`).
5. **Migrasi** (hanya MENAMBAH 4 kolom ke tabel `templates`; data lama tidak diubah/dihapus):
   ```bat
   php artisan migrate
   ```
6. **Template bawaan & admin:** 
   ```bat
   php artisan db:seed
   ```
   Seeder membuat 5 template "Night City" (tidak menimpa template bernama sama) dan akun admin **hanya bila belum ada**.
   Jika `ADMIN_PASSWORD` kosong, kata sandi acak ditampilkan sekali di terminal. Akun admin lama tetap berlaku.
7. **Jalankan:**
   ```bat
   php artisan optimize:clear
   php artisan serve
   ```
   Buka **http://127.0.0.1:8000** atau **http://localhost:8000**. Tidak perlu `storage:link`: file hasil
   disajikan lewat route `/media/...`.

### Kamera: syarat browser
`getUserMedia` hanya jalan di **HTTPS atau localhost**. Untuk booth di laptop yang sama gunakan
`http://localhost:8000`. Membuka lewat IP LAN (`http://192.168.x.x:8000`) dari perangkat lain **tidak** bisa memakai kamera;
pakai tunnel HTTPS (ngrok) bila perlu. Kamera DSLR (mis. Canon EOS 1200D) harus tampil sebagai webcam
(EOS Webcam Utility); pilih di dropdown "Kamera" pada halaman sesi.

## 2. Variabel `.env` baru

| Variabel | Fungsi |
|---|---|
| `PHOTOBOOTH_PUBLIC_URL` | Alamat dasar untuk QR code, mis. `https://abc.ngrok-free.app`. Wajib diisi bila booth dibuka via `127.0.0.1` tetapi ponsel pengunjung harus bisa membuka hasilnya. |
| `PHOTOBOOTH_COUNTDOWN` | Hitung mundur default (detik), default 3. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Akun yang dibuat seeder (bila belum ada). |
| `APP_TIMEZONE` | Contoh `Asia/Jakarta`. Default tetap `UTC` agar data lama tidak bergeser; bila diubah, jam sesi lama tampil bergeser 7 jam. |
| `SESSION_DRIVER` | Disarankan `file` untuk kiosk (login/CSRF tetap jalan saat MySQL bermasalah). |

## 3. Yang berubah (ringkas)

**Dibuat:** `config/photobooth.php`, `MediaController`, `TemplateSeeder` + `database/seeders/data/` (5 bingkai PNG + `templates.json`),
migration `2026_10_22_000001_add_gallery_fields_to_templates_table`, `public/assets/{css,js,img}`, `resources/views/partials/*`,
`admin/templates/{_form,edit}.blade.php`, `vendor/pagination/pixel.blade.php`, `tests/Feature/PhotoboothTest.php`.

**Ditulis ulang:** semua view Blade dan layout; `PhotoboothController`, `SessionController`, `AuthController`,
`Admin\DashboardController`, `Admin\TemplateController`; model `Template` & `PhotoSession`; `routes/web.php`; `bootstrap/app.php`;
`DatabaseSeeder`; `.env.example`; `public/favicon.ico` (sebelumnya kosong).

**Diubah kecil:** `config/app.php` (timezone dari env), `phpunit.xml` (SQLite in-memory aktif — sebelumnya `RefreshDatabase`
akan mengenai database MySQL asli).

**Dihapus dari paket:** `public/hot` (sisa dev server Vite yang membuat CSS gagal dimuat saat `npm run dev` mati),
`public/build` (aset Tailwind lama), `ngrok.exe`, `.env`, cache, log, `database.sqlite`.
`resources/css/app.css`, `resources/js/*`, `package.json`, `vite.config.js` **tetap ada tetapi tidak lagi dipanggil** layout.

### Bug yang ditemukan di versi sebelumnya dan diperbaiki
- Layout admin memanggil `route('dashboard')` yang tidak ada → semua halaman admin error.
- `public/hot` + manifest Vite hilang → CSS/JS tidak termuat (error "Vite manifest not found" muncul 4× di log).
- `public/storage` berupa folder kosong, bukan symlink → gambar tidak tampil.
- Foto digepengkan ke ukuran slot (`resize`) → sekarang dipotong tengah (`cover`) sesuai rasio slot.
- `process()` tanpa validasi/batas jumlah/cek MIME; foto base64 mentah lewat form → sekarang multipart + validasi + throttle.
- `layout_config` tersimpan double-encoded (string JSON di dalam JSON) → dibaca dengan fallback, disimpan benar.
- Statistik dashboard belum terhubung ke data; daftar template tanpa ubah/hapus/aktif-nonaktif.
- Pagination bawaan Laravel butuh Tailwind → diganti tampilan sendiri.

## 4. Verifikasi setelah instal (jalankan sendiri)

```bat
php artisan route:list          :: harus memuat photobooth.*, admin.*, media, csrf
php artisan migrate:status      :: migration 2026_10_22_000001 berstatus Ran
php artisan test                :: memakai SQLite in-memory, tidak menyentuh MySQL
```
Lalu manual: buka `/` → Mulai → pilih template → izinkan kamera → ambil semua foto → Simpan → cek `/admin/sessions` (login admin).

## 5. Sistem desain & animasi

- Token di `public/assets/css/pixel-core.css` (`:root`): palet disampel dari poster — `--night-*` (langit), `--peri-*` (judul),
  `--pink/peach/cream` (awan), `--lamp-*` (lampu jalan, aksen aksi utama), `--green/--magenta` (status).
  Tipografi: *Press Start 2P* (judul/tombol) + *Pixelify Sans* (isi); radius 0; tepi bertakik ala piksel 4 px.
- Gerak: `--dur-fast/--dur/--dur-slow` (120/220/420 ms) dan `--ease-out`; reveal bertahap, hover-lift kartu, countdown skala+fade,
  flash 260 ms, pop thumbnail, "cetak" strip (`clip-path` bertahap) di halaman hasil, transisi fade antarhalaman.
  Seluruhnya CSS; JS hanya untuk sinkronisasi dengan proses kamera. `prefers-reduced-motion` mematikan animasi non-esensial.
- Font memakai Google Fonts. Untuk event **offline**, pasang kedua font di OS komputer booth (atau lihat komentar di `pixel-core.css`);
  tanpa itu tampil font monospace cadangan.
- Aset pixel-art (skyline, awan, bulan, bintang, 5 bingkai) dibuat prosedural; palet dari poster.

## 6. Integrasi LumaBooth

| Dari Luma | Di proyek ini |
|---|---|
| `engine/gallery.js` (tab genre, pencarian, urutan, kartu strip) | `public/assets/js/gallery.js` — data dari DB Laravel, kartu dibuat sekali (tanpa kedip saat filter), dialog preview `<dialog>` |
| `engine/booth.js` (countdown, flash, retake per slot, preview strip) | `public/assets/js/booth.js` — ditambah panduan crop sesuai rasio slot, pemilihan kamera, penanganan error kamera, upload ke Laravel |
| Strip CSS per template | `strip.js` — komposisi persentase 1200×1800 yang sama dengan hitungan server |
| Unggah foto manual | Dipertahankan sebagai cadangan bila kamera gagal |
| **Tidak diadopsi:** filter foto, editor, hosting statis | Di luar lingkup alur Laravel ini |

## 7. Status pengujian

**Lulus (Chromium headless + kamera palsu, halaman Blade dirender kasar ke HTML statis + server tiruan):**
countdown 3-2-1, flash, 4 foto + strip ganda, retake satu foto, panduan crop & rasio berkas = rasio slot (tanpa gepeng),
upload FormData (4 file JPEG, header CSRF/Accept), gagal 500 (foto tetap ada), 419 → token disegarkan → terkirim,
pesan error (izin ditolak, kamera dipakai, konteks tidak aman, tidak ada kamera, kamera dicabut), `pagehide` menghentikan track,
Esc/Spasi, unggah manual, galeri (filter, cari, urut, dialog, keyboard, fokus kembali), layout 390/820/1366/1440 px tanpa overflow horizontal,
pratinjau tata letak admin.

**Belum dijalankan:** `php artisan route:list / migrate:status / test`, `php -l` (hanya pemeriksa kurung kasar yang dijalankan),
komposisi gambar Intervention di server, QR code, login/CSRF Laravel sungguhan, kamera DSLR Canon, cetak 4R, Safari/iOS & Firefox.

## 8. Kendala yang masih tersisa
- Kode PHP perlu diverifikasi di mesinmu (bagian 4); kemungkinan ada galat kecil yang hanya terlihat saat dijalankan.
- Pratinjau browser dan hasil server memakai geometri sama, tetapi tampilan akhir bingkai di server belum dibandingkan piksel demi piksel.
- Akses kamera dari perangkat lain lewat HTTP biasa tetap diblokir browser (batasan browser, bukan bug).
- Jumlah foto maksimal 4 per template (aturan validasi admin).
- Foto hasil tersimpan tanpa batas waktu; belum ada fitur hapus sesi/retensi otomatis.
