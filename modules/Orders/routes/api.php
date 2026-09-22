<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderController;

Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::post('/', [OrderController::class, 'store'])->name('store');
    Route::get('{order}', [OrderController::class, 'show'])->name('show');
    Route::patch('{order}/status', [OrderController::class, 'updateStatus'])->name('status');
});
