<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all — Vue Router handles all frontend routes
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum).*$');
