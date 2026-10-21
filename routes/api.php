<?php

use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\TerminalController;
use Illuminate\Support\Facades\Route;

// POS devices. Pairing swaps a one-time code for a device token; everything else needs that token.
Route::prefix('v1/terminal')->group(function () {
    Route::post('pair', [TerminalController::class, 'pair'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'terminal'])->group(function () {
        Route::get('me', [TerminalController::class, 'me']);
        Route::get('staff', [TerminalController::class, 'staff']);
        Route::post('pin-login', [TerminalController::class, 'pinLogin']);
        Route::get('catalog', [CatalogController::class, 'index']);
        Route::get('barcode/{code}', [CatalogController::class, 'barcode']);
    });
});
