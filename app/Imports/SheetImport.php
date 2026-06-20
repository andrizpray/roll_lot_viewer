<?php

namespace App\Imports;

use App\Models\ImportError;
use App\Models\PaperSheet;
use App\Services\SheetDescriptionParser;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Importable;

class SheetImport implements ToModel, WithChunkReading
{
    use Importable;

    private ?array $columnMap = null;
    private bool $headerSkipped = false;
    private int $rowIndex = 0;
    public int $successCount = 0;
    public int $failedCount = 0;
    public int $totalRows = 0;

    public function __construct(
        private int $importBatchId,
        private SheetDescriptionParser $parser
    ) {}

    public function model(array $row)
    {
        // Build column map from first row
        if ($this->columnMap === null) {
            $this->columnMap = $this->buildColumnMap($row);

            // Check if this is a header row (cell at lot_id index == 'LotID')
            $lotIdIdx = $this->columnMap['lot_id'] ?? null;
            if ($lotIdIdx !== null && isset($row[$lotIdIdx]) && trim((string) $row[$lotIdIdx]) === 'LotID') {
                $this->headerSkipped = true;
                return null;
            }

            // Not a header row, process it
            $this->headerSkipped = true;
        }

        $this->rowIndex++;
        $rowNumber = $this->rowIndex + 1;

        // Extract fields using column map
        $lotId = $this->columnMap['lot_id'] !== null ? ($row[$this->columnMap['lot_id']] ?? null) : null;
        $itemId = $this->columnMap['item_id'] !== null ? ($row[$this->columnMap['item_id']] ?? null) : null;
        $weight = $this->columnMap['weight'] !== null ? ($row[$this->columnMap['weight']] ?? null) : null;
        $description = $this->columnMap['description'] !== null ? ($row[$this->columnMap['description']] ?? null) : null;
        $papertype = $this->columnMap['papertype'] !== null ? ($row[$this->columnMap['papertype']] ?? null) : null;
        $contentPack = $this->columnMap['qty_pack'] !== null ? ($row[$this->columnMap['qty_pack']] ?? null) : null;
        $contentPallet = $this->columnMap['qty'] !== null ? ($row[$this->columnMap['qty']] ?? null) : null;
        $trDate = $this->columnMap['tr_date'] !== null ? ($row[$this->columnMap['tr_date']] ?? null) : null;
        $trTime = $this->columnMap['tr_time'] !== null ? ($row[$this->columnMap['tr_time']] ?? null) : null;

        // Skip empty rows
        if (empty($lotId) && empty($itemId) && empty($weight)) {
            return null;
        }

        $this->totalRows++;

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
                'reason' => 'Description cannot be parsed (kurang dari 2 kata)',
            ]);
            $this->failedCount++;
            return null;
        }

        $this->successCount++;

        // Clean papertype: jika value berupa formula Excel (=...) atau
        // terlalu panjang, ganti dengan null. Extract dari gramature jika
        // masih kosong (misal "BK350" → "BK").
        $cleanPapertype = is_string($papertype) ? trim($papertype) : null;
        if ($cleanPapertype !== null && (str_starts_with($cleanPapertype, '=') || strlen($cleanPapertype) > 50)) {
            $cleanPapertype = null;
        }
        if ($cleanPapertype === null && !empty($parsed['gramature'])) {
            // Extract papertype prefix dari gramature (BK350 → BK)
            preg_match('/^([A-Za-z]+)/', $parsed['gramature'], $m);
            $cleanPapertype = $m[1] ?? null;
        }

        return new PaperSheet([
            'lot_id' => $lotId,
            'item_id' => $itemId,
            'weight' => $weight,
            'papertype' => $cleanPapertype,
            'gramature' => $parsed['gramature'],
            'dimension' => $parsed['dimension'],
            'content_pack' => is_numeric($contentPack) ? (int) $contentPack : null,
            'content_pallet' => is_numeric($contentPallet) ? (int) $contentPallet : null,
            'description_raw' => $parsed['description_raw'],
            'source_tr_date' => $trDate ? \Carbon\Carbon::parse($trDate)->format('Y-m-d') : null,
            'source_tr_time' => $trTime,
            'import_batch_id' => $this->importBatchId,
        ]);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    protected function buildColumnMap(array $headerRow): array
    {
        $lookup = [];
        foreach ($headerRow as $idx => $value) {
            if (is_string($value)) {
                $lookup[strtolower(trim($value))] = $idx;
            }
        }

        $findExact = function (array $candidates) use ($lookup) {
            foreach ($candidates as $name) {
                if (array_key_exists($name, $lookup)) {
                    return $lookup[$name];
                }
            }
            return null;
        };

        return [
            'lot_id' => $findExact(['lotid']) ?? 1,
            'item_id' => $findExact(['itemid']) ?? 2,
            'qty' => $findExact(['qty']),
            'weight' => $findExact(['weight']) ?? 4,
            'tr_date' => $findExact(['trdate']),
            'tr_time' => $findExact(['trtime']),
            'qty_pack' => $findExact(['qty_pack', 'qtypack']),
            'description' => $findExact(['description']) ?? 9,
            'papertype' => count($headerRow) - 1,
        ];
    }
}
