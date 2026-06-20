<?php

namespace App\Imports;

use App\Models\ImportError;
use App\Models\PaperSheet;
use App\Services\SheetDescriptionParser;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Events\BeforeReading;

class SheetImport implements ToModel, WithChunkReading, WithEvents
{
    use Importable;

    private ?array $columnMap = null;
    private bool $headerSkipped = false;
    private int $rowIndex = 0;
    public int $successCount = 0;
    public int $failedCount = 0;
    public int $totalRows = 0;
    public array $processedLotIds = [];

    public function __construct(
        private int $importBatchId,
        private SheetDescriptionParser $parser
    ) {}

    public function model(array $row)
    {
        // Build column map from first row
        if ($this->columnMap === null) {
            $this->columnMap = $this->buildColumnMap($row);

            // Check if this is a header row by looking for common header patterns
            $lotIdIdx = $this->columnMap['lot_id'] ?? null;
            $itemIdIdx = $this->columnMap['item_id'] ?? null;
            $descIdx = $this->columnMap['description'] ?? null;
            
            $isHeader = false;
            if ($lotIdIdx !== null && isset($row[$lotIdIdx])) {
                $lotVal = strtolower(trim((string) $row[$lotIdIdx]));
                if (in_array($lotVal, ['lotid', 'lot id', 'lot_id', 'lotnumber', 'no lot'])) {
                    $isHeader = true;
                }
            }
            // Also check if multiple header-like values exist
            if (!$isHeader && $itemIdIdx !== null && isset($row[$itemIdIdx])) {
                $itemVal = strtolower(trim((string) $row[$itemIdIdx]));
                if (in_array($itemVal, ['itemid', 'item id', 'item_id', 'item_no', 'no item'])) {
                    $isHeader = true;
                }
            }
            if (!$isHeader && $descIdx !== null && isset($row[$descIdx])) {
                $descVal = strtolower(trim((string) $row[$descIdx]));
                if (in_array($descVal, ['description', 'desc', 'paper', 'jenis', 'keterangan'])) {
                    $isHeader = true;
                }
            }

            if ($isHeader) {
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

        // Track processed lot_ids for smart merge cleanup
        $this->processedLotIds[] = $lotId;

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

        // Upsert: update if exists, create if not
        PaperSheet::updateOrCreate(
            ['lot_id' => $lotId],
            [
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
            ]
        );

        // Return null since we handle persistence via updateOrCreate above
        return null;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Configure reader: skip styles (ReadDataOnly) + limit columns (A-L).
     * Cuts memory ~70% for .xls files.
     */
    public function registerEvents(): array
    {
        return [
            BeforeReading::class => function (BeforeReading $event) {
                $event->reader->setReadDataOnly(true);
                $event->reader->setReadFilter(new LightReadFilter(13)); // A-M
            },
        ];
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

        // Try to find LotID column
        $lotIdIdx = $findExact(['lotid', 'lot id', 'lot_id', 'lotnumber']);
        
        // If no header found, try to detect by content pattern (LotID starts with LM, LM0, etc.)
        if ($lotIdIdx === null) {
            foreach ($headerRow as $idx => $value) {
                if (is_string($value) && preg_match('/^LM\d{8,}/i', trim($value))) {
                    $lotIdIdx = $idx;
                    break;
                }
            }
        }
        
        // Find ItemID column - numeric values around 9-10 digits
        $itemIdIdx = $findExact(['itemid', 'item id', 'item_id', 'item_no']);
        if ($itemIdIdx === null) {
            foreach ($headerRow as $idx => $value) {
                if (is_numeric($value) && strlen((string)$value) >= 8 && strlen((string)$value) <= 12) {
                    // Skip if this is likely weight (small decimal)
                    if (is_float($value) && $value < 1000) continue;
                    $itemIdIdx = $idx;
                    break;
                }
            }
        }

        // Find Weight column - decimal values in typical weight range
        $weightIdx = $findExact(['weight', 'wt', 'berat', 'weight_kg']);
        if ($weightIdx === null) {
            foreach ($headerRow as $idx => $value) {
                if (is_numeric($value) && $value > 10 && $value < 10000 && strpos((string)$value, '.') !== false) {
                    $weightIdx = $idx;
                    break;
                }
            }
        }

        // Find Description column - text with paper type codes
        $descIdx = $findExact(['description', 'desc', 'paper', 'jenis']);
        if ($descIdx === null) {
            foreach ($headerRow as $idx => $value) {
                if (is_string($value) && preg_match('/BK|BM|CW|Kraft|Liner/i', $value)) {
                    $descIdx = $idx;
                    break;
                }
            }
        }

        return [
            'lot_id' => $lotIdIdx ?? 0,
            'item_id' => $itemIdIdx ?? 1,
            'qty' => $findExact(['qty', 'quantity', 'jumlah']),
            'weight' => $weightIdx ?? 2,
            'tr_date' => $findExact(['trdate', 'tr_date', 'date', 'tanggal']),
            'tr_time' => $findExact(['trtime', 'tr_time', 'time', 'waktu']),
            'qty_pack' => $findExact(['qty_pack', 'qtypack', 'pack']),
            'description' => $descIdx ?? 3,
            'papertype' => $findExact(['papertype', 'paper_type', 'jenis_kertas']) ?? (count($headerRow) - 1),
        ];
    }
}
