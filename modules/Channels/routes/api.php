<?php

use Illuminate\Support\Facades\Route;
use Modules\Channels\Http\Controllers\ChannelOrderController;
use Modules\Channels\Http\Controllers\WebhookController;

Route::prefix('channels')->name('channels.')->group(function () {
    // A webhookot a webshop hívja: aláírással hitelesít, nem munkamenettel.
    Route::post('{channel}/orders', WebhookController::class)->middleware('throttle:120,1')->name('webhook');
    Route::get('orders', [ChannelOrderController::class, 'index'])->name('orders.index');
    Route::get('report', [ChannelOrderController::class, 'report'])->name('report');
});
