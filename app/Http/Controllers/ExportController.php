<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function export(Request $request)
    {
        try {
            $mode = $request->get('mode', 'advanced');
            $resource = $request->get('resource', 'roll');

            // Build filters array instead of running queries
            $filters = $this->buildFilters($request, $mode, $resource);

            // Create export job record for Python worker
            $jobId = DB::table('export_jobs')->insertGetId([
                'type' => $resource,
                'filters' => json_encode([
                    'mode' => $mode,
                    'resource' => $resource,
                    'params' => $filters,
                ]),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Export job created',
                'job_id' => $jobId,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Export job creation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function status($id)
    {
        $job = DB::table('export_jobs')->find($id);

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'filename' => $job->filename,
            'total_rows' => $job->total_rows,
            'error_message' => $job->error_message,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        ]);
    }

    public function download($id)
    {
        $job = DB::table('export_jobs')->find($id);

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        if ($job->status !== 'completed') {
            return response()->json(['error' => 'Export not ready', 'status' => $job->status], 400);
        }

        if (empty($job->filename)) {
            return response()->json(['error' => 'No filename set for completed job'], 400);
        }

        $path = Storage::disk('public')->path($job->filename);

        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found on disk'], 404);
        }

        return response()->download($path, $job->filename)->deleteFileAfterSend(true);
    }

    /**
     * Build filter array from request params based on mode and resource type.
     * Does NOT run queries — just extracts the filter params.
     */
    protected function buildFilters(Request $request, string $mode, string $resource): array
    {
        if ($mode === 'batch') {
            return $this->buildBatchFilters($request);
        }

        if ($resource === 'sheet') {
            return $this->buildAdvancedSheetFilters($request);
        }

        return $this->buildAdvancedRollFilters($request);
    }

    protected function buildBatchFilters(Request $request): array
    {
        $input = $request->get('lot_ids', '');
        $lotIds = preg_split('/[\s,;]+/', trim($input));
        $lotIds = array_filter(array_map('trim', $lotIds));
        $lotIds = array_unique(array_map('strtoupper', $lotIds));
        $lotIds = array_values(array_slice($lotIds, 0, 1000));

        return ['lot_ids' => $lotIds];
    }

    protected function buildAdvancedRollFilters(Request $request): array
    {
        return $request->only([
            'item_id', 'grade', 'papertype', 'gramature', 'width',
            'date_from', 'date_to', 'lot_id',
        ]);
    }

    protected function buildAdvancedSheetFilters(Request $request): array
    {
        return $request->only([
            'item_id', 'papertype', 'gramature', 'dimension',
            'date_from', 'date_to', 'lot_id',
        ]);
    }
}
