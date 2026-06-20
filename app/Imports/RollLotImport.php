<?php

namespace App\Imports;

use App\Models\ImportError;
use App\Models\RollLot;
use App\Services\DescriptionParser;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Importable;

class RollLotImport implements ToModel, WithChunkReading
{
    use Importable;

    private bool $headerSkipped = false;
    private int $rowIndex = 0;
    public int $successCount = 0;
    public int $failedCount = 0;
    public array $processedLotIds = [];

    public function __construct(
        private int $importBatchId,
        private DescriptionParser $parser
    ) {}

    public function model(array $row)
    {
        // Skip header row (first row where first cell is 'LotID' or 'LotId' or 'lotid')
        if (!$this->headerSkipped) {
            $firstCell = trim(strtolower((string)($row[0] ?? '')));
            if ($firstCell === 'lotid') {
                $this->headerSkipped = true;
                return null;
            }
            // If not header, still mark as skipped so we don't check again
            $this->headerSkipped = true;
        }

        $this->rowIndex++;
        $rowNumber = $this->rowIndex + 1; // +1 for header

        // Skip empty rows
        if (empty($row[0] ?? null) && empty($row[1] ?? null) && empty($row[2] ?? null)) {
            return null;
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
            $this->failedCount++;
            return null;
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
            $this->failedCount++;
            return null;
        }

        // Parse description
        $parsed = $this->parser->parse($description);

        if ($parsed === null) {
            ImportError::create([
                'import_batch_id' => $this->importBatchId,
                'row_number' => $rowNumber,
                'lot_id' => $lotId,
                'description_raw' => $description,
                'reason' => 'Description cannot be parsed (less than 4 words or invalid format)',
            ]);
            $this->failedCount++;
            return null;
        }

        $this->successCount++;

        // Track processed lot_ids for smart merge cleanup
        $this->processedLotIds[] = $lotId;

        // Upsert: update if exists, create if not
        RollLot::updateOrCreate(
            ['lot_id' => $lotId],
            [
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
            ]
        );

        // Return null since we handle persistence via updateOrCreate above
        return null;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
