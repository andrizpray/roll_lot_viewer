<?php

namespace App\Services;

class ExcelTypeDetector
{
    public const TYPE_ROLL = 'roll';

    public const TYPE_SHEET = 'sheet';

    /**
     * Deteksi tipe file mutasi berdasarkan header kolom pada baris pertama
     * sheet "Data".
     *
     * - File "Mutasi Roll" (PM1/PM2): header sudah punya kolom 'PaperType'
     *   dan 'Gramature' terpisah, plus 'RewID'.
     * - File "Mutasi Stock Sheet": header tidak punya 'PaperType' terpisah,
     *   tapi punya 'Qty_Pack' dan 'Description' yang masih gabungan.
     *
     * @param array $headerRow Baris header pertama (array of string|null)
     * @return string self::TYPE_ROLL | self::TYPE_SHEET
     */
    public function detect(array $headerRow): string
    {
        $normalized = array_map(function ($value) {
            return is_string($value) ? strtolower(trim($value)) : $value;
        }, $headerRow);

        $hasRollMarkers = in_array('papertype', $normalized, true)
            && in_array('gramature', $normalized, true)
            && in_array('rewid', $normalized, true);

        if ($hasRollMarkers) {
            return self::TYPE_ROLL;
        }

        $hasSheetMarkers = in_array('qty_pack', $normalized, true)
            && (in_array('description', $normalized, true)
                || in_array('keterangan', $normalized, true));

        if ($hasSheetMarkers) {
            return self::TYPE_SHEET;
        }

        // Default fallback: anggap roll (perilaku lama) supaya file lama tetap
        // diproses seperti sebelumnya kalau header tidak cocok keduanya.
        return self::TYPE_ROLL;
    }
}
