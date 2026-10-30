<?php

return [

    /*
    | Ukuran kanvas hasil akhir (cetak 4R, 300 dpi). Dipakai server saat
    | menyusun strip dan oleh pratinjau di browser — jangan diubah sepihak.
    */
    'canvas' => [
        'width'  => 1200,
        'height' => 1800,
    ],

    /*
    | Lama hitung mundur default (detik) sebelum foto diambil.
    */
    'countdown' => (int) env('PHOTOBOOTH_COUNTDOWN', 3),

    /*
    | Mode transisi antar foto di bilik kamera:
    | 'auto'   => Langsung melanjutkan timer ke foto berikutnya secara otomatis tanpa pop-up.
    | 'manual' => Berhenti setelah tiap foto, menunggu pengguna klik Lanjut / tekan Spasi.
    */
    'advance_mode' => env('PHOTOBOOTH_ADVANCE_MODE', 'auto'),

    /*
    | Alamat dasar untuk QR code dan tautan bagikan, mis. "https://abc123.ngrok-free.app"
    | atau "http://192.168.1.10:8000". Kosongkan agar memakai alamat yang sedang dibuka di browser.
    | Isi bila booth dibuka lewat 127.0.0.1 tetapi ponsel pengunjung harus bisa membuka hasilnya.
    */
    'public_url' => env('PHOTOBOOTH_PUBLIC_URL'),

    /*
    | Akun admin yang dibuat oleh seeder (hanya bila belum ada).
    | Jika ADMIN_PASSWORD kosong, seeder membuat sandi acak dan menampilkannya sekali di terminal.
    */
    'admin' => [
        'email'    => env('ADMIN_EMAIL', 'admin@photobooth.tik'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Kamera Canon lewat digiCamControl (memakai EDSDK Canon). Nyalakan dengan DIGICAM_ENABLED=true.
    | Server Laravel meneruskan perintah ke web server digiCamControl di laptop yang sama,
    | sehingga browser tidak terkena batasan CORS / campuran HTTP-HTTPS saat dibuka lewat tunnel.
    */
    'digicam' => [
        'enabled'         => (bool) env('DIGICAM_ENABLED', false),
        'url'             => env('DIGICAM_URL', 'http://127.0.0.1:5513'),
        'default_source'  => env('DIGICAM_DEFAULT_SOURCE', 'digicam'),   // digicam | webcam
        'capture_timeout' => (int) env('DIGICAM_CAPTURE_TIMEOUT', 25),    // detik menunggu foto muncul
        'live_interval'   => (int) env('DIGICAM_LIVE_INTERVAL', 120),     // ms antar frame live view
    ],

    /*
    | Pengaturan Kamera Hub (Siaran kamera admin ke bilik user via WebRTC & HTTP buffer)
    */
    'camera_hub' => [
        'live_interval' => (int) env('CAMERA_HUB_LIVE_INTERVAL', 75),      // ms antar frame fallback HTTP
    ],

    /*
    | Pengaturan Integrasi Google Drive API
    */
    'google_drive' => [
        'enabled'              => (bool) env('GOOGLE_DRIVE_ENABLED', false),
        'folder_id'            => env('GOOGLE_DRIVE_FOLDER_ID', ''),
        'client_id'            => env('GOOGLE_DRIVE_CLIENT_ID', ''),
        'client_secret'        => env('GOOGLE_DRIVE_CLIENT_SECRET', ''),
        'refresh_token'        => env('GOOGLE_DRIVE_REFRESH_TOKEN', ''),
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON', ''),
        'webhook_url'          => env('GOOGLE_DRIVE_WEBHOOK_URL', ''),
        'auto_upload'          => (bool) env('GOOGLE_DRIVE_AUTO_UPLOAD', true),
    ],

    'categories' => [
        'strip'  => 'Strip',
        'grid'   => 'Grid',
        'duo'    => 'Duo',
        'single' => 'Tunggal',
        'umum'   => 'Umum',
    ],
];
