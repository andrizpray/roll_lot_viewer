<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Jobs\ProcessExcelImport;
use App\Jobs\ProcessSheetImport;
use App\Services\ExcelTypeDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    /**
     * Upload and process Excel file.
     *
     * Tipe file (Roll / Sheet) dideteksi otomatis dari header kolom pada
     * sheet "Data", supaya user tidak perlu pilih tipe secara manual.
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

        // Deteksi tipe file dari header sheet "Data" sebelum dispatch job
        $type = $this->detectType(Storage::path($path));

        // Create import batch record
        $batch = ImportBatch::create([
            'filename' => $filename,
            'type' => $type,
            'status' => 'processing',
        ]);

        // Dispatch job sesuai tipe yang terdeteksi
        if ($type === ExcelTypeDetector::TYPE_SHEET) {
            ProcessSheetImport::dispatch($batch->id, Storage::path($path));
        } else {
            ProcessExcelImport::dispatch($batch->id, Storage::path($path));
        }

        return response()->json([
            'message' => 'File uploaded successfully. Processing in background.',
            'batch_id' => $batch->id,
            'type' => $type,
        ], 202);
    }

    /**
     * Baca header baris pertama sheet "Data" (atau sheet pertama bila tidak
     * ada sheet bernama "Data") lalu tentukan tipenya.
     */
    protected function detectType(string $filePath): string
    {
        try {
            $sheets = Excel::toArray([], $filePath);
            // Laravel Excel mengembalikan array of sheets (terurut sesuai
            // urutan di file); ambil sheet pertama sebagai default.
            $firstSheet = $sheets[0] ?? [];
            $headerRow = $firstSheet[0] ?? [];

            return (new ExcelTypeDetector())->detect($headerRow);
        } catch (\Throwable $e) {
            // Kalau gagal dibaca di sini, biarkan job yang dispatch nanti
            // menangkap errornya sendiri secara lebih detail. Default ke roll.
            return ExcelTypeDetector::TYPE_ROLL;
        }
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
            'type' => $batch->type,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'success_count' => $batch->success_count,
            'failed_count' => $batch->failed_count,
        ]);
    }
}
