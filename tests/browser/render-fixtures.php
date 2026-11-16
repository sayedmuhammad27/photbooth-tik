<?php

// Render the real Blade pages with an isolated in-memory database for browser tests.
foreach ([
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'APP_URL' => 'http://localhost', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'DIGICAM_ENABLED' => 'true',
] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
session()->start();
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$admin = App\Models\User::factory()->create();
auth()->login($admin);
$template = App\Models\Template::create([
    'name' => 'Browser test', 'is_active' => true, 'slot_count' => 1, 'bg_color' => '#131947',
    'layout_config' => [['x' => 50, 'y' => 260, 'width' => 1100, 'height' => 1200, 'photo' => 0]],
]);
$directory = storage_path('app/runtime/browser');
if (! is_dir($directory)) { mkdir($directory, 0755, true); }
file_put_contents($directory.'/hub.html', (new App\Http\Controllers\CameraHubController)->index()->render());
file_put_contents($directory.'/booth.html', (new App\Http\Controllers\TestPhotoController)->create($template)->render());
file_put_contents($directory.'/welcome.html', (new App\Http\Controllers\PhotoboothController)->index(request())->render());
$image = imagecreatetruecolor(640, 480);
imagefill($image, 0, 0, imagecolorallocate($image, 180, 60, 50));
imagejpeg($image, $directory.'/frame.jpg', 90);
imagedestroy($image);
echo "Browser fixtures rendered.\n";
