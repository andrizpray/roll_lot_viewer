<?php

namespace App\Http\Controllers;

use App\Models\ImportJob;
use App\Services\ExcelTypeDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $originalName = $file->getClientOriginalName();

        // Generate a unique storage name to avoid collisions
        $storageName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);

        // Store file in storage/app/uploads/ — this is where the Python worker looks
        $uploadsDir = storage_path('app/uploads');
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0775, true);
        }
        $file->move($uploadsDir, $storageName);

        $fullPath = $uploadsDir . '/' . $storageName;

        // Validate content: ensure the file has recognized Excel columns
        $validation = $this->validateExcelContent($fullPath);
        if (!$validation['valid']) {
            // Clean up the uploaded file
            @unlink($fullPath);
            return response()->json([
                'error'   => 'File tidak dikenali sebagai data mutasi yang valid.',
                'details' => $validation['reason'],
            ], 422);
        }

        // Detect type from Excel headers
        $type = $validation['type'];

        // Create job record for Python worker
        // filename = storageName so worker can find the file in UPLOAD_DIR
        $job = ImportJob::create([
            'filename' => $storageName,
            'type'     => $type,
            'status'   => 'pending',
        ]);

        return response()->json([
            'message'  => 'File uploaded successfully. Waiting for worker to process.',
            'job_id'   => $job->id,
            'filename' => $originalName,
            'type'     => $type,
        ], 202);
    }

    /**
     * Validate Excel content by checking headers for recognized columns.
     * Returns ['valid' => bool, 'type' => string, 'reason' => string|null]
     */
    protected function validateExcelContent(string $filePath): array
    {
        // Known column headers that the Python worker can process
        $recognizedHeaders = [
            'lot id', 'item id', 'weight', 'paper type', 'gramature',
            'play bond', 'width', 'rew id', 'grade', 'comments',
            'diameter', 'thickness', 'description', 'date', 'time',
            'qty_pack', 'qty_pallet', 'keterangan',
        ];

        try {
            $sheets = Excel::toArray([], $filePath);
            $firstSheet = $sheets[0] ?? [];
            $headerRow = $firstSheet[0] ?? [];

            if (empty($headerRow)) {
                return ['valid' => false, 'type' => null, 'reason' => 'File kosong atau tidak memiliki header row.'];
            }

            // Count how many recognized columns are present
            $normalized = array_map(fn($v) => is_string($v) ? strtolower(trim($v)) : '', $headerRow);
            $matched = array_intersect($normalized, $recognizedHeaders);

            if (count($matched) < 3) {
                return [
                    'valid'  => false,
                    'type'   => null,
                    'reason' => 'Header kolom tidak dikenali. Minimal 3 kolom standar diperlukan (Lot ID, Item ID, Weight, dll). '
                              . 'Ditemukan: ' . implode(', ', array_filter($headerRow)),
                ];
            }

            // Detect type
            $type = (new ExcelTypeDetector())->detect($headerRow);

            return ['valid' => true, 'type' => $type, 'reason' => null];
        } catch (\Throwable $e) {
            return ['valid' => false, 'type' => null, 'reason' => 'Gagal membaca file Excel: ' . $e->getMessage()];
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

    /**
     * Download Roll Lot template Excel file.
     */
    public function downloadRollTemplate()
    {
        $filepath = storage_path('app/templates/template_roll_lot.xlsx');
        
        if (!file_exists($filepath)) {
            return response()->json(['error' => 'Template file not found'], 404);
        }

        return response()->download($filepath, 'Template_Roll_Lot.xlsx');
    }

    /**
     * Download Paper Sheet template Excel file.
     */
    public function downloadSheetTemplate()
    {
        $filepath = storage_path('app/templates/template_paper_sheet.xlsx');
        
        if (!file_exists($filepath)) {
            return response()->json(['error' => 'Template file not found'], 404);
        }

        return response()->download($filepath, 'Template_Paper_Sheet.xlsx');
    }
}
