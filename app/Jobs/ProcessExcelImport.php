<?php

namespace App\Jobs;

use App\Imports\RollLotImport;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\RollLot;
use App\Services\DescriptionParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExcelImport implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public $maxExceptions = 3;

    protected $importBatchId;

    protected $filePath;

    /**
     * Create a new job instance.
     */
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
        $parser = new DescriptionParser();

        try {
            // Snapshot existing lot_ids before import (with lock to prevent race condition)
            $oldLotIds = DB::transaction(function () {
                return RollLot::lockForUpdate()->pluck('lot_id')->toArray();
            });

            // Use chunked import to avoid loading entire Excel file into memory
            // WithChunkReading processes in 500-row batches with low memory footprint
            $import = new RollLotImport($this->importBatchId, $parser);
            Excel::import($import, $this->filePath);

            // Delete lot_ids that no longer appear in the new file
            $newLotIds = $import->processedLotIds;
            $toDelete = array_diff($oldLotIds, $newLotIds);
            if (!empty($toDelete)) {
                RollLot::whereIn('lot_id', $toDelete)->delete();
            }

            $successCount = $import->successCount;
            $failedCount = $import->failedCount;

            // Update batch status
            $batch->update([
                'total_rows' => $successCount + $failedCount,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'status' => 'success',
            ]);

        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'failed',
            ]);

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
