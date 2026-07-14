<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\RollLotController;
use App\Http\Controllers\PaperSheetController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;

// Health check — no auth required (for monitoring tools)
Route::get('/health', HealthController::class);

// All API routes protected by API key
Route::middleware('api_key')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'summary']);

    // Import routes
    Route::post('/imports', [ImportController::class, 'upload'])
        ->middleware('throttle:20,1'); // max 20 uploads per minute per IP
    Route::get('/imports', [ImportController::class, 'index']);
    Route::get('/imports/{id}', [ImportController::class, 'show']);
    Route::get('/imports/{id}/status', [ImportController::class, 'status']);
    
    // Template download routes
    Route::get('/templates/roll-lot', [ImportController::class, 'downloadRollTemplate']);
    Route::get('/templates/sheet', [ImportController::class, 'downloadSheetTemplate']);

    // Roll lots routes
    Route::get('/roll-lots', [RollLotController::class, 'index']);
    Route::get('/roll-lots/distinct-values', [RollLotController::class, 'distinctValues']);
    Route::get('/roll-lots/{id}', [RollLotController::class, 'show']);

    // Paper sheets routes
    Route::get('/sheets', [PaperSheetController::class, 'index']);
    Route::get('/sheets/distinct-values', [PaperSheetController::class, 'distinctValues']);
    Route::get('/sheets/{id}', [PaperSheetController::class, 'show']);

    // Export routes
    Route::get('/export', [ExportController::class, 'export']);
    Route::get('/export/{id}/status', [ExportController::class, 'status']);
    Route::get('/export/{id}/download', [ExportController::class, 'download']);
});
