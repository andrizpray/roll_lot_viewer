<?php

namespace App\Jobs;

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
        $totalRows = 0;
        $successCount = 0;
        $failedCount = 0;

        try {
            $rows = Excel::toArray([], $this->filePath)[0];

            $dataRows = array_values(array_filter($rows, function ($row) {
                return !empty($row) && !empty($row[1] ?? null); // kolom LotID
            }));

            if (count($dataRows) === 0) {
                throw new \RuntimeException('File tidak memiliki baris data.');
            }

            $headerRow = $dataRows[0];
            $columnMap = $this->buildColumnMap($headerRow);

            // Baris pertama adalah header (cek kolom LotID == 'LotID')
            if (($dataRows[0][$columnMap['lot_id']] ?? null) === 'LotID') {
                array_shift($dataRows);
            }

            while (count($dataRows) > 0 && $this->isEmptyRow(end($dataRows))) {
                array_pop($dataRows);
            }

            $totalRows = count($dataRows);

            DB::transaction(function () use ($dataRows, $columnMap, $parser, &$successCount, &$failedCount) {
                foreach ($dataRows as $index => $row) {
                    $rowNumber = $index + 2;

                    if ($this->isEmptyRow($row)) {
                        continue;
                    }

                    $lotId = $row[$columnMap['lot_id']] ?? null;
                    $itemId = $row[$columnMap['item_id']] ?? null;
                    $weight = $row[$columnMap['weight']] ?? null;
                    $description = $row[$columnMap['description']] ?? null;
                    $papertype = $columnMap['papertype'] !== null ? ($row[$columnMap['papertype']] ?? null) : null;
                    $qtyPack = $columnMap['qty_pack'] !== null ? ($row[$columnMap['qty_pack']] ?? null) : null;
                    $qtyPallet = $columnMap['qty'] !== null ? ($row[$columnMap['qty']] ?? null) : null;
                    $trDate = $columnMap['tr_date'] !== null ? ($row[$columnMap['tr_date']] ?? null) : null;
                    $trTime = $columnMap['tr_time'] !== null ? ($row[$columnMap['tr_time']] ?? null) : null;

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

                    $parsed = $parser->parse($description);

                    if ($parsed === null) {
                        ImportError::create([
                            'import_batch_id' => $this->importBatchId,
                            'row_number' => $rowNumber,
                            'lot_id' => $lotId,
                            'description_raw' => $description,
                            'reason' => 'Description cannot be parsed (kurang dari 2 kata)',
                        ]);
                        $failedCount++;
                        continue;
                    }

                    PaperSheet::create([
                        'lot_id' => $lotId,
                        'item_id' => $itemId,
                        'weight' => $weight,
                        'papertype' => is_string($papertype) ? trim($papertype) : $papertype,
                        'gramature' => $parsed['gramature'],
                        'dimension' => $parsed['dimension'],
                        'content_pack' => is_numeric($qtyPack) ? (int) $qtyPack : null,
                        'content_pallet' => is_numeric($qtyPallet) ? (int) $qtyPallet : null,
                        'description_raw' => $parsed['description_raw'],
                        'source_tr_date' => $trDate ? \Carbon\Carbon::parse($trDate)->format('Y-m-d') : null,
                        'source_tr_time' => $trTime,
                        'import_batch_id' => $this->importBatchId,
                    ]);

                    $successCount++;
                }
            });

            $batch->update([
                'total_rows' => $totalRows,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
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

    /**
     * Bangun pemetaan nama kolom -> index, berdasarkan baris header.
     * Kolom kategori/papertype headernya tidak konsisten (kadang berupa angka
     * hasil merge cell), jadi diasumsikan sebagai kolom terakhir.
     */
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
            // Kolom kategori/papertype: header-nya tidak bisa diandalkan
            // (sering berupa angka karena merged cell), jadi pakai kolom
            // paling kanan pada baris header sebagai fallback.
            'papertype' => count($headerRow) - 1,
        ];
    }

    protected function isEmptyRow(array $row): bool
    {
        return count(array_filter($row, fn ($value) => !empty($value))) === 0;
    }
}
