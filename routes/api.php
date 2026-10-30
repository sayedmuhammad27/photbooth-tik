<?php

use App\Http\Controllers\Api\GoogleDriveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Photobooth TIK
|--------------------------------------------------------------------------
*/

Route::prefix('sessions')->group(function () {
    Route::post('{token}/upload-drive', [GoogleDriveController::class, 'uploadSession'])->name('api.sessions.upload-drive');
    Route::get('{token}/drive', [GoogleDriveController::class, 'getSessionStatus'])->name('api.sessions.drive-status');
});

Route::prefix('google-drive')->group(function () {
    Route::post('upload', [GoogleDriveController::class, 'directUpload'])->name('api.google-drive.upload');
    Route::get('status', [GoogleDriveController::class, 'status'])->name('api.google-drive.status');
});
