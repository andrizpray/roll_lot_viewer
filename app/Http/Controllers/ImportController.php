<?php

namespace App\Http\Controllers;

use App\Models\ImportJob;
use App\Services\ExcelTypeDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    /**
     * Upload Excel file, detect type, create import_jobs record.
     * Python worker picks it up and processes it.
     */
    public function upload(Request $request)
    {
        ini_set('memory_limit', '512M');

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480', // max 20MB
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        // Store file in uploads directory
        $path = $file->store('imports');

        // Detect type from Excel headers
        $type = $this->detectType(Storage::path($path));

        // Create job record for Python worker
        $job = ImportJob::create([
            'filename' => $filename,
            'type'     => $type,
            'status'   => 'pending',
        ]);

        return response()->json([
            'message'  => 'File uploaded successfully. Waiting for worker to process.',
            'job_id'   => $job->id,
            'filename' => $filename,
            'type'     => $type,
        ], 202);
    }

    /**
     * Read first header row of sheet "Data" (or first sheet if none named
     * "Data") and determine type.
     */
    protected function detectType(string $filePath): string
    {
        try {
            $sheets = Excel::toArray([], $filePath);
            $firstSheet = $sheets[0] ?? [];
            $headerRow = $firstSheet[0] ?? [];

            return (new ExcelTypeDetector())->detect($headerRow);
        } catch (\Throwable $e) {
            return ExcelTypeDetector::TYPE_ROLL;
        }
    }

    /**
     * List all import jobs with pagination.
     */
    public function index(Request $request)
    {
        $jobs = ImportJob::orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($jobs);
    }

    /**
     * Get single import job details.
     */
    public function show($id)
    {
        $job = ImportJob::findOrFail($id);

        return response()->json($job);
    }

    /**
     * Get job status for polling.
     */
    public function status($id)
    {
        $job = ImportJob::findOrFail($id);

        return response()->json([
            'id'            => $job->id,
            'filename'      => $job->filename,
            'type'          => $job->type,
            'status'        => $job->status,
            'total_rows'    => $job->total_rows,
            'success_count' => $job->success_count,
            'failed_count'  => $job->failed_count,
        ]);
    }
}
