<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\QueueController as AdminQueueController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CameraHubController;
use App\Http\Controllers\DigicamController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PhotoboothController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TestPhotoController;
use Illuminate\Support\Facades\Route;

// ==========================================
// HALAMAN PELANGGAN / USER
// ==========================================
Route::get('/', [PhotoboothController::class, 'index'])->name('home');
Route::get('/setup', [PhotoboothController::class, 'setup'])->name('photobooth.setup');
Route::get('/session/{template}', [SessionController::class, 'create'])->name('photobooth.session');
Route::post('/session/{template}/process', [SessionController::class, 'process'])
    ->middleware('throttle:booth-process')
    ->name('photobooth.process');
Route::get('/result/{token}', [SessionController::class, 'result'])->name('photobooth.result');
Route::get('/result/{token}/download/{kind}', [SessionController::class, 'download'])
    ->where('kind', 'strip|[0-9]+')
    ->name('photobooth.download');

// ==========================================
// FITUR TEST FOTO (UJI COBA TANPA SIMPAN DATABASE)
// ==========================================
Route::prefix('test-photo')->name('test.photo.')->group(function () {
    Route::get('/', [TestPhotoController::class, 'setup'])->name('setup');
    Route::get('/session/{template}', [TestPhotoController::class, 'create'])->name('session');
    Route::post('/session/{template}/process', [TestPhotoController::class, 'process'])
        ->middleware('throttle:booth-process')
        ->name('process');
    Route::get('/result/{token}', [TestPhotoController::class, 'result'])->name('result');
    Route::get('/download/{token}', [TestPhotoController::class, 'download'])->name('download');
});

// ==========================================
// SISTEM ANTREAN PUBLIK
// ==========================================
Route::post('/queue/take', [QueueController::class, 'take'])->name('queue.take');
Route::get('/queue/status', [QueueController::class, 'status'])->name('queue.status');
Route::get('/queue/check/{number}', [QueueController::class, 'check'])->name('queue.check');
Route::post('/queue/verify', [QueueController::class, 'verify'])->name('queue.verify');

// ==========================================
// KAMERA HUB (AKSES KAMERA ADMIN DARI BILIK USER)
// ==========================================
Route::prefix('camera-hub')->name('camera.hub.')->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    // These endpoints were already CSRF-exempt; also skip the session-backed cookie writer.
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
])->group(function () {
    Route::post('/push', [CameraHubController::class, 'pushFrame'])->name('push');
    Route::get('/frame', [CameraHubController::class, 'getFrame'])->name('frame');
    Route::post('/trigger', [CameraHubController::class, 'triggerCapture'])->name('trigger');
    Route::post('/snapshot/push', [CameraHubController::class, 'pushSnapshot'])->name('snapshot.push');
    Route::get('/snapshot', [CameraHubController::class, 'getSnapshot'])->name('snapshot');
    Route::get('/status', [CameraHubController::class, 'status'])->name('status');
    Route::match(['get', 'post'], '/signal', [CameraHubController::class, 'signal'])->name('signal');
});

// Token CSRF segar untuk halaman kamera yang dibiarkan terbuka lama (kiosk)
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf');

// Jembatan ke digiCamControl (kamera Canon). Aktif hanya bila DIGICAM_ENABLED=true
// dan hanya untuk sesi browser yang sudah membuka halaman kamera.
Route::prefix('digicam')->name('digicam.')->middleware('throttle:digicam-live')->group(function () {
    Route::get('/status', [DigicamController::class, 'status'])->name('status');
    Route::get('/liveview', [DigicamController::class, 'liveview'])->name('liveview');
    Route::post('/liveview/start', [DigicamController::class, 'liveviewStart'])->name('liveview.start');
    Route::post('/liveview/stop', [DigicamController::class, 'liveviewStop'])->name('liveview.stop');
    Route::post('/capture', [DigicamController::class, 'capture'])->middleware('throttle:digicam-capture')->name('capture');
});

// File hasil & bingkai (tanpa perlu symlink storage:link)
Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '(?:results|templates)/[A-Za-z0-9._-]+')
    ->name('media');

// ==========================================
// AUTENTIKASI ADMIN
// ==========================================
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'authenticate'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ==========================================
// DASHBOARD & MANAJEMEN ADMIN (Dilindungi Auth)
// ==========================================
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/sessions', [DashboardController::class, 'sessions'])->name('sessions');

    // Kamera Hub Admin (Siarkan kamera admin ke bilik)
    Route::get('/camera-hub', [CameraHubController::class, 'index'])->name('camera.hub');

    // Manajemen Antrean
    Route::prefix('queue')->name('queue.')->group(function () {
        Route::get('/', [AdminQueueController::class, 'index'])->name('index');
        Route::post('/toggle', [AdminQueueController::class, 'toggleStatus'])->name('toggle');
        Route::post('/call-next', [AdminQueueController::class, 'callNext'])->name('call-next');
        Route::post('/set-specific', [AdminQueueController::class, 'setSpecific'])->name('set-specific');
        Route::post('/manual', [AdminQueueController::class, 'storeManual'])->name('manual');
        Route::post('/reset-day', [AdminQueueController::class, 'resetDay'])->name('reset-day');
        Route::get('/live-data', [AdminQueueController::class, 'liveData'])->name('live-data');
        Route::post('/{ticket}/start', [AdminQueueController::class, 'startSession'])->name('start');
        Route::post('/{ticket}/complete', [AdminQueueController::class, 'complete'])->name('complete');
        Route::post('/{ticket}/skip', [AdminQueueController::class, 'skip'])->name('skip');
        Route::post('/{ticket}/reset', [AdminQueueController::class, 'resetTicket'])->name('reset');
    });

    // Manajemen Template (CRUD + aktif/nonaktif)
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::patch('/templates/{template}/toggle', [TemplateController::class, 'toggle'])->name('templates.toggle');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
});
