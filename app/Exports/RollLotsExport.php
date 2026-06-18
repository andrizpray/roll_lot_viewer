<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RollLotsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'LotID',
            'ItemID',
            'Weight',
            'PaperType',
            'Gramature',
            'Plybond',
            'Width',
            'RewID',
            'Grade',
            'Comment',
            'Diameter',
        ];
    }

    public function map($row): array
    {
        return [
            $row->lot_id,
            $row->item_id,
            $row->weight,
            $row->papertype,
            $row->gramature,
            $row->playbond,
            $row->width,
            $row->rew_id,
            $row->grade,
            $row->comments,
            $row->diameter,
        ];
    }
}
