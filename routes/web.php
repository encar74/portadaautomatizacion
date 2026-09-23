<?php

use App\Http\Controllers\ArticleEditorialController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\GeneratedArticleController;
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
    Route::get('articles', [GeneratedArticleController::class, 'index'])->name('generated-articles.index');
    Route::put('articles/{article}', [ArticleEditorialController::class, 'update'])->name('generated-articles.update');
    Route::post('articles/{article}/ai-correction', [ArticleEditorialController::class, 'requestAiCorrection'])->name('generated-articles.ai-correction');
    Route::post('articles/{article}/wordpress-draft', [ArticleEditorialController::class, 'publish'])->name('generated-articles.wordpress-draft');
    Route::get('press-releases', [PressReleaseController::class, 'index'])->name('press-releases.index');
    Route::get('press-releases/{pressRelease}', [PressReleaseController::class, 'show'])->name('press-releases.show');
    Route::get('press-releases/{pressRelease}/attachments/{attachment}', [PressReleaseController::class, 'download'])->name('press-releases.attachments.download');
});
