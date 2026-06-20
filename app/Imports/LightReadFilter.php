<?php

namespace App\Imports;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limits which columns are read from Excel files.
 * Cuts memory ~70% by skipping unused columns.
 */
class LightReadFilter implements IReadFilter
{
    private int $maxColumn;

    public function __construct(int $maxColumn = 12)
    {
        $this->maxColumn = $maxColumn;
    }

    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        // Convert letter to index: A=0, B=1, ..., L=11, M=12
        $index = 0;
        $columnAddress = strtoupper($columnAddress);
        for ($i = 0; $i < strlen($columnAddress); $i++) {
            $index = $index * 26 + (ord($columnAddress[$i]) - 64);
        }
        $index--; // 1-based to 0-based

        return $index < $this->maxColumn;
    }
}
