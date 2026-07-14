<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint for monitoring.
     *
     * Checks: Laravel app, SQLite connectivity, Python worker heartbeat, disk space.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // 1. Database connectivity
        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // 2. Python worker heartbeat
        // Worker writes a heartbeat file on each poll cycle
        $heartbeatFile = storage_path('app/worker_heartbeat');
        if (file_exists($heartbeatFile)) {
            $lastBeat = (int) file_get_contents($heartbeatFile);
            $age = time() - $lastBeat;
            if ($age < 30) {
                $checks['worker'] = ['status' => 'ok', 'last_heartbeat_seconds_ago' => $age];
            } else {
                $checks['worker'] = ['status' => 'stale', 'last_heartbeat_seconds_ago' => $age];
                $healthy = false;
            }
        } else {
            $checks['worker'] = ['status' => 'unknown', 'message' => 'No heartbeat file found'];
        }

        // 3. Disk space
        $storagePath = storage_path();
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);
        $freeGb = round($freeBytes / 1073741824, 2);
        $usagePercent = round((1 - $freeBytes / $totalBytes) * 100, 1);

        if ($freeGb < 1) {
            $checks['disk'] = ['status' => 'critical', 'free_gb' => $freeGb, 'usage_percent' => $usagePercent];
            $healthy = false;
        } elseif ($freeGb < 5) {
            $checks['disk'] = ['status' => 'warning', 'free_gb' => $freeGb, 'usage_percent' => $usagePercent];
        } else {
            $checks['disk'] = ['status' => 'ok', 'free_gb' => $freeGb, 'usage_percent' => $usagePercent];
        }

        // 4. Pending jobs (queue depth)
        try {
            $pendingImports = DB::table('import_jobs')->where('status', 'pending')->count();
            $pendingExports = DB::table('export_jobs')->where('status', 'pending')->count();
            $checks['queue'] = [
                'status' => 'ok',
                'pending_imports' => $pendingImports,
                'pending_exports' => $pendingExports,
            ];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        return response()->json([
            'healthy' => $healthy,
            'checks'  => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
