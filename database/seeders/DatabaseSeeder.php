<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->call(TemplateSeeder::class);
    }

    /** Buat akun admin hanya bila belum ada; akun yang sudah ada tidak diubah. */
    private function seedAdmin(): void
    {
        $email = config('photobooth.admin.email');
        $password = config('photobooth.admin.password') ?: Str::password(14, symbols: false);

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrator', 'password' => $password]   // cast "hashed" menghash otomatis
        );

        if ($user->wasRecentlyCreated && ! config('photobooth.admin.password')) {
            $this->command?->warn("Akun admin dibuat. Email: {$email} | Kata sandi: {$password}");
            $this->command?->warn('Catat kata sandi ini, tidak akan ditampilkan lagi. Atau isi ADMIN_PASSWORD di .env sebelum seeding.');
        }
    }
}
