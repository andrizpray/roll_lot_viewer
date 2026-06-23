<?php

namespace App\Jobs;

use App\Imports\SheetImport;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\PaperSheet;
use App\Services\SheetDescriptionParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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
     * Smart merge strategy:
     * 1. Collect existing lot_ids before import
     * 2. Run import with updateOrCreate (upsert per row)
     * 3. Delete lot_ids that were present before but NOT in the new file
     */
    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->importBatchId);
        $parser = new SheetDescriptionParser();

        try {
            // Snapshot existing lot_ids before import (with lock to prevent race condition)
            $oldLotIds = DB::transaction(function () {
                return PaperSheet::lockForUpdate()->pluck('lot_id')->toArray();
            });

            $sheetImport = new SheetImport($this->importBatchId, $parser);
            Excel::import($sheetImport, $this->filePath);

            // Delete lot_ids that no longer appear in the new file
            $newLotIds = $sheetImport->processedLotIds;
            $toDelete = array_diff($oldLotIds, $newLotIds);
            if (!empty($toDelete)) {
                PaperSheet::whereIn('lot_id', $toDelete)->delete();
            }

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
