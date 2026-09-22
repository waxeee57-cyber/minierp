<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\CustomerController;
use Modules\Crm\Http\Controllers\CustomerSummaryController;
use Modules\Crm\Http\Controllers\InteractionController;

Route::prefix('crm')->name('crm.')->group(function () {
    Route::apiResource('customers', CustomerController::class)->except('destroy');
    Route::post('customers/{customer}/interactions', [InteractionController::class, 'store'])->name('customers.interactions.store');
    Route::patch('customers/{customer}/interactions/{interaction}/complete', [InteractionController::class, 'complete'])->name('customers.interactions.complete');
    Route::get('customers/{customer}/summary', CustomerSummaryController::class)
        ->middleware('throttle:ai')
        ->name('customers.summary');
});
