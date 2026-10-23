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

    'categories' => [
        'strip'  => 'Strip',
        'grid'   => 'Grid',
        'duo'    => 'Duo',
        'single' => 'Tunggal',
        'umum'   => 'Umum',
    ],
];
