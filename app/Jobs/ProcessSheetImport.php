<?php

namespace App\Jobs;

use App\Imports\SheetImport;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Services\SheetDescriptionParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;

class ProcessSheetImport implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public $maxExceptions = 3;

    protected $importBatchId;

    protected $filePath;

    public function __construct(int $importBatchId, string $filePath)
    {
        $this->importBatchId = $importBatchId;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     *
     * Catatan: berbeda dari roll lots, data sheet ini bersifat APPEND
     * (menambah), bukan replace/truncate — karena "Mutasi Stock Sheet"
     * adalah laporan harian dan biasanya tidak menggantikan seluruh histori.
     * Kalau ke depannya perilakunya juga harus replace seperti roll, ganti
     * bagian "Simpan data" di bawah ini untuk truncate dulu seperti pada
     * ProcessExcelImport.
     */
    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->importBatchId);
        $parser = new SheetDescriptionParser();

        try {
            $sheetImport = new SheetImport($this->importBatchId, $parser);
            Excel::import($sheetImport, $this->filePath);

            $batch->update([
                'total_rows' => $sheetImport->totalRows,
                'success_count' => $sheetImport->successCount,
                'failed_count' => $sheetImport->failedCount,
                'status' => 'success',
            ]);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed']);

            ImportError::create([
                'import_batch_id' => $this->importBatchId,
                'row_number' => 0,
                'description_raw' => null,
                'reason' => 'Import failed: ' . $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
