<?php

namespace App\Jobs;

use App\Imports\RollLotImport;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\RollLot;
use App\Models\RollLotHistory;
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
     */
    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->importBatchId);
        $parser = new DescriptionParser();
        $successCount = 0;
        $failedCount = 0;

        try {
            // Snapshot must run BEFORE delete because TRUNCATE/DELETE causes
            // implicit commit in MySQL/InnoDB, which breaks outer transactions.
            // Snapshot is archived to roll_lot_history for backup purposes.
            $this->createSnapshot();

            // Delete all existing roll lots
            RollLot::query()->delete();

            // Use chunked import to avoid loading entire Excel file into memory
            // WithChunkReading processes in 500-row batches with low memory footprint
            $import = new RollLotImport($this->importBatchId, $parser);
            Excel::import($import, $this->filePath);
            
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

    /**
     * Create snapshot of current roll_lots before replace.
     */
    protected function createSnapshot(): void
    {
        $currentTime = now();

        RollLot::chunk(500, function ($lots) use ($currentTime) {
            foreach ($lots as $lot) {
                RollLotHistory::create([
                    'lot_id' => $lot->lot_id,
                    'item_id' => $lot->item_id,
                    'weight' => $lot->weight,
                    'papertype' => $lot->papertype,
                    'gramature' => $lot->gramature,
                    'playbond' => $lot->playbond,
                    'width' => $lot->width,
                    'rew_id' => $lot->rew_id,
                    'grade' => $lot->grade,
                    'comments' => $lot->comments,
                    'diameter' => is_numeric($lot->diameter) ? $lot->diameter : null,
                    'thickness' => $lot->thickness,
                    'description_raw' => $lot->description_raw,
                    'source_tr_date' => $lot->source_tr_date,
                    'source_tr_time' => $lot->source_tr_time,
                    'import_batch_id' => $lot->import_batch_id,
                    'archived_at' => $currentTime,
                ]);
            }
        });
    }
}
