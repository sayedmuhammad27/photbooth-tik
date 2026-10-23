<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Menyajikan file dari storage/app/public secara langsung sehingga aplikasi
 * tidak bergantung pada symlink `php artisan storage:link` (sering gagal di Windows).
 * Hanya folder results/ dan templates/ dengan nama file aman yang bisa diakses.
 */
class MediaController extends Controller
{
    public function show(string $path)
    {
        abort_unless(preg_match('#^(results|templates)/[A-Za-z0-9._-]+$#', $path) === 1, 404);
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control'          => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
