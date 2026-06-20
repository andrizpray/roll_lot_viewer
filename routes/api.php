<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\RollLotController;
use App\Http\Controllers\PaperSheetController;
use App\Http\Controllers\ExportController;

// Import routes
Route::post('/imports', [ImportController::class, 'upload']);
Route::get('/imports', [ImportController::class, 'index']);
Route::get('/imports/{id}', [ImportController::class, 'show']);
Route::get('/imports/{id}/status', [ImportController::class, 'status']);

// Roll lots routes
Route::get('/roll-lots', [RollLotController::class, 'index']);
Route::get('/roll-lots/{id}', [RollLotController::class, 'show']);

// Paper sheets routes
Route::get('/sheets', [PaperSheetController::class, 'index']);
Route::get('/sheets/{id}', [PaperSheetController::class, 'show']);

// Export route
Route::get('/export', [ExportController::class, 'export']);
