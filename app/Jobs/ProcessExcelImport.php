<?php

namespace App\Jobs;

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
        $totalRows = 0;
        $successCount = 0;
        $failedCount = 0;

        try {
            // Load Excel file using Laravel Excel
            $rows = Excel::toArray([], $this->filePath)[0];

            // Remove empty rows first
            $dataRows = array_filter($rows, function ($row) {
                return !empty($row) && !empty($row[0] ?? null);
            });

            // Re-index after filter
            $dataRows = array_values($dataRows);

            // Skip header row explicitly (first row after filtering)
            if (count($dataRows) > 0 && $dataRows[0][0] === 'LotID') {
                array_shift($dataRows);
            }

            // Remove trailing empty rows (last 3 usually)
            while (count($dataRows) > 0 && $this->isEmptyRow(end($dataRows))) {
                array_pop($dataRows);
            }

            $totalRows = count($dataRows);

            // Create snapshot of current roll_lots before replace
            $this->createSnapshot();

            // Truncate roll_lots
            RollLot::query()->truncate();

            // Process each row
            foreach ($dataRows as $index => $row) {
                $rowNumber = $index + 2; // +2 because array is 0-indexed but Excel is 1-indexed + header

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $lotId = $row[0] ?? null;
                $itemId = $row[1] ?? null;
                $weight = $row[2] ?? null;
                $rewId = $row[3] ?? null;
                $trDate = $row[4] ?? null;
                $trTime = $row[5] ?? null;
                $description = $row[6] ?? null;
                $diameter = $row[7] ?? null;
                $thickness = $row[8] ?? null;
                $grade = $row[9] ?? null;
                $comments = $row[10] ?? null;

                // Validate required fields
                if (empty($lotId) || empty($itemId) || empty($weight) || empty($description)) {
                    ImportError::create([
                        'import_batch_id' => $this->importBatchId,
                        'row_number' => $rowNumber,
                        'lot_id' => $lotId,
                        'description_raw' => $description,
                        'reason' => 'Missing required fields (LotID, ItemID, Weight, Description)',
                    ]);
                    $failedCount++;
                    continue;
                }

                // Validate weight is numeric
                if (!is_numeric($weight)) {
                    ImportError::create([
                        'import_batch_id' => $this->importBatchId,
                        'row_number' => $rowNumber,
                        'lot_id' => $lotId,
                        'description_raw' => $description,
                        'reason' => 'Weight is not a valid number: ' . $weight,
                    ]);
                    $failedCount++;
                    continue;
                }

                // Parse description
                $parsed = $parser->parse($description);

                if ($parsed === null) {
                    ImportError::create([
                        'import_batch_id' => $this->importBatchId,
                        'row_number' => $rowNumber,
                        'lot_id' => $lotId,
                        'description_raw' => $description,
                        'reason' => 'Description cannot be parsed (less than 4 words or invalid format)',
                    ]);
                    $failedCount++;
                    continue;
                }

                // Create roll lot
                RollLot::create([
                    'lot_id' => $lotId,
                    'item_id' => $itemId,
                    'weight' => $weight,
                    'papertype' => $parsed['papertype'],
                    'gramature' => $parsed['gramature'],
                    'playbond' => $parsed['playbond'],
                    'width' => $parsed['width'],
                    'rew_id' => $rewId,
                    'grade' => $grade,
                    'comments' => $comments,
                    'diameter' => is_numeric($diameter) ? $diameter : null,
                    'thickness' => $thickness,
                    'description_raw' => $parsed['description_raw'],
                    'source_tr_date' => $trDate ? \Carbon\Carbon::parse($trDate)->format('Y-m-d') : null,
                    'source_tr_time' => $trTime,
                    'import_batch_id' => $this->importBatchId,
                ]);

                $successCount++;
            }

            // Update batch status
            $batch->update([
                'total_rows' => $totalRows,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'status' => 'success',
            ]);

        } catch (\Exception $e) {

            $batch->update([
                'status' => 'failed',
            ]);

            ImportError::create([
                'import_batch_id' => $this->importBatchId,
                'row_number' => 0,
                'description_raw' => $e->getMessage(),
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

        $existingLots = RollLot::all();

        foreach ($existingLots as $lot) {
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
    }

    /**
     * Check if a row is empty.
     */
    protected function isEmptyRow(array $row): bool
    {
        return count(array_filter($row, fn($value) => !empty($value))) === 0;
    }
}
