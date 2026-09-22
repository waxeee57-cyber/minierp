<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\ForecastController;
use Modules\Inventory\Http\Controllers\ProductController;
use Modules\Inventory\Http\Controllers\StockMovementController;

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('forecast', ForecastController::class)->name('forecast');
    Route::apiResource('products', ProductController::class)->except('destroy');
    Route::post('products/{product}/movements', [StockMovementController::class, 'store'])->name('products.movements.store');
});
