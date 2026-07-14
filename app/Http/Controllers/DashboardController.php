<?php

namespace App\Http\Controllers;

use App\Models\ImportJob;
use App\Models\PaperSheet;
use App\Models\RollLot;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Return summary statistics for the dashboard.
     */
    public function summary(): JsonResponse
    {
        // Roll lot stats
        $rollTotal = RollLot::count();
        $rollTotalWeight = (float) RollLot::sum('weight');

        // Sheet stats
        $sheetTotal = PaperSheet::count();
        $sheetTotalWeight = (float) PaperSheet::sum('weight');

        // Import job stats (Python worker writes to import_jobs)
        $importTotal = ImportJob::count();
        $importSuccess = ImportJob::where('status', 'completed')->count();
        $importFailed = ImportJob::where('status', 'failed')->count();

        // Imports today
        $importsToday = ImportJob::whereDate('created_at', today())->count();

        // Recent imports (last 10)
        $recentImports = ImportJob::orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'filename', 'type', 'status', 'total_rows', 'success_count', 'failed_count', 'created_at']);

        return response()->json([
            'roll' => [
                'total' => $rollTotal,
                'total_weight' => $rollTotalWeight,
            ],
            'sheet' => [
                'total' => $sheetTotal,
                'total_weight' => $sheetTotalWeight,
            ],
            'imports' => [
                'total' => $importTotal,
                'success' => $importSuccess,
                'failed' => $importFailed,
                'recent' => $recentImports,
            ],
            'imports_today' => $importsToday,
        ]);
    }
}
