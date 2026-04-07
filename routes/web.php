<?php

use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/video');

Route::get('/video', [VideoController::class, 'index'])->name('video.index');

Route::post('/video/generate', [VideoController::class, 'generate'])
    ->name('video.generate')
    ->middleware('throttle:10,1');

Route::get('/video/status/{id}', [VideoController::class, 'status'])
    ->name('video.status')
    ->middleware('throttle:120,1');

Route::get('/video/{id}', [VideoController::class, 'show'])->name('video.show');

Route::get('/video/{id}/download', [VideoController::class, 'download'])->name('video.download');

Route::get('/video/{id}/tts/{sceneNumber}', [VideoController::class, 'tts'])
    ->name('video.tts')
    ->middleware('throttle:120,1');
