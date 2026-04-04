<?php

use App\Http\Controllers\N8NCallbackController;
use Illuminate\Support\Facades\Route;

Route::middleware(['n8n.secret'])->group(function () {
    Route::post('/n8n/callback', [N8NCallbackController::class, 'callback'])->name('n8n.callback');
    Route::post('/n8n/error',    [N8NCallbackController::class, 'error'])->name('n8n.error');
    Route::post('/n8n/step',     [N8NCallbackController::class, 'step'])->name('n8n.step');
});
