<?php

use Illuminate\Support\Facades\Route;
use Modules\Channels\Http\Controllers\FeedController;

Route::get('feeds/google-merchant.xml', [FeedController::class, 'google'])->name('feeds.google');
Route::get('feeds/arukereso.xml', [FeedController::class, 'arukereso'])->name('feeds.arukereso');
