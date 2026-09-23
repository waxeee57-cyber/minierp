<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// A modulok saját route-jaikat a providerükből töltik be; itt csak a modulok fölötti nézetek élnek.
Route::get('dashboard', DashboardController::class)->name('dashboard');
Route::get('search', SearchController::class)->middleware('throttle:120,1')->name('search');
