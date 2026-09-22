<?php

use Illuminate\Support\Facades\Route;
use Modules\Assistant\Http\Controllers\AskController;

Route::post('assistant/ask', AskController::class)->middleware('throttle:ai')->name('assistant.ask');
