<?php

use Illuminate\Support\Facades\Route;

// Egyoldalas Vue.js felület: minden nem-API útvonal a SPA-t kapja, a mélylinkeket a vue-router oldja fel.
Route::view('/{path?}', 'app')->where('path', '^(?!api/|build/|storage/).*$')->name('app');
