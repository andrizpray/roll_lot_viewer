<?php

namespace App\Http\Controllers;

use App\Exports\RollLotsExport;
use App\Models\RollLot;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    /**
     * Export roll lots as XLSX based on active filter mode.
     */
    public function export(Request $request)
    {
        $mode = $request->get('mode', 'advanced');

        if ($mode === 'batch') {
            $rows = $this->getBatchRows($request);
        } else {
            $rows = $this->getAdvancedRows($request);
        }

        $filename = 'roll_lots_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new RollLotsExport($rows), $filename);
    }

    protected function getBatchRows(Request $request)
    {
        $input = $request->get('lot_ids', '');
        $lotIds = preg_split('/[\s,;]+/', trim($input));
        $lotIds = array_filter(array_map('trim', $lotIds));
        $lotIds = array_values(array_unique(array_map('strtoupper', $lotIds)));

        if (empty($lotIds)) return collect();

        return RollLot::searchByLotIds($lotIds)->get();
    }

    protected function getAdvancedRows(Request $request)
    {
        $filters = $request->only(['item_id', 'papertype', 'grade', 'date_from', 'date_to', 'gramature', 'width', 'lot_id']);

        $query = RollLot::query();

        if (!empty($filters['item_id'])) $query->where('item_id', $filters['item_id']);
        if (!empty($filters['papertype'])) $query->where('papertype', 'like', '%' . $filters['papertype'] . '%');
        if (!empty($filters['grade'])) $query->where('grade', $filters['grade']);
        if (!empty($filters['date_from'])) $query->whereDate('source_tr_date', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('source_tr_date', '<=', $filters['date_to']);
        if (!empty($filters['gramature'])) $query->where('gramature', 'like', '%' . $filters['gramature'] . '%');
        if (!empty($filters['width'])) $query->where('width', $filters['width']);
        if (!empty($filters['lot_id'])) $query->where('lot_id', 'like', '%' . $filters['lot_id'] . '%');

        return $query->orderBy('lot_id')->get();
    }
}
