<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PhotoboothController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

// ==========================================
// HALAMAN PELANGGAN / USER
// ==========================================
Route::get('/', [PhotoboothController::class, 'index'])->name('home');
Route::get('/setup', [PhotoboothController::class, 'setup'])->name('photobooth.setup');
Route::get('/session/{template}', [SessionController::class, 'create'])->name('photobooth.session');
Route::post('/session/{template}/process', [SessionController::class, 'process'])
    ->middleware('throttle:30,1')
    ->name('photobooth.process');
Route::get('/result/{token}', [SessionController::class, 'result'])->name('photobooth.result');
Route::get('/result/{token}/download/{kind}', [SessionController::class, 'download'])
    ->where('kind', 'strip|[0-9]+')
    ->name('photobooth.download');

// Token CSRF segar untuk halaman kamera yang dibiarkan terbuka lama (kiosk)
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf');

// File hasil & bingkai (tanpa perlu symlink storage:link)
Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '(?:results|templates)/[A-Za-z0-9._-]+')
    ->name('media');

// ==========================================
// AUTENTIKASI ADMIN
// ==========================================
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'authenticate'])->middleware(['guest', 'throttle:8,1']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ==========================================
// DASHBOARD & MANAJEMEN ADMIN (Dilindungi Auth)
// ==========================================
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/sessions', [DashboardController::class, 'sessions'])->name('sessions');

    // Manajemen Template (CRUD + aktif/nonaktif)
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::patch('/templates/{template}/toggle', [TemplateController::class, 'toggle'])->name('templates.toggle');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
});
