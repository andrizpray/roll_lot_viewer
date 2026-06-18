<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Jobs\ProcessExcelImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    /**
     * Upload and process Excel file.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480', // max 20MB
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        // Store file temporarily
        $path = $file->store('imports');

        // Create import batch record
        $batch = ImportBatch::create([
            'filename' => $filename,
            'status' => 'processing',
        ]);

        // Dispatch job
        ProcessExcelImport::dispatch($batch->id, Storage::path($path));

        return response()->json([
            'message' => 'File uploaded successfully. Processing in background.',
            'batch_id' => $batch->id,
        ], 202);
    }

    /**
     * Get import batch history with pagination.
     */
    public function index(Request $request)
    {
        $batches = ImportBatch::orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($batches);
    }

    /**
     * Get single import batch details with errors.
     */
    public function show($id)
    {
        $batch = ImportBatch::with('errors')->findOrFail($id);

        return response()->json($batch);
    }

    /**
     * Get import batch status (for polling during processing).
     */
    public function status($id)
    {
        $batch = ImportBatch::findOrFail($id);

        return response()->json([
            'id' => $batch->id,
            'filename' => $batch->filename,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'success_count' => $batch->success_count,
            'failed_count' => $batch->failed_count,
        ]);
    }
}
