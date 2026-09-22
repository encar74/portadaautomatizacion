<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\PressReleaseController;
use App\Http\Controllers\PressSourceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/press-sources');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::resource('press-sources', PressSourceController::class)->except('show');
    Route::get('press-releases', [PressReleaseController::class, 'index'])->name('press-releases.index');
    Route::get('press-releases/{pressRelease}', [PressReleaseController::class, 'show'])->name('press-releases.show');
    Route::get('press-releases/{pressRelease}/attachments/{attachment}', [PressReleaseController::class, 'download'])->name('press-releases.attachments.download');
});
