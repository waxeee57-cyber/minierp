<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// A modulok saját route-jaikat a providerükből töltik be; itt csak a modulok fölötti nézet él.
Route::get('dashboard', DashboardController::class)->name('dashboard');
