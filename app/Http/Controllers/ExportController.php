<?php

namespace App\Http\Controllers;

use App\Exports\RollLotsExport;
use App\Exports\SheetsExport;
use App\Models\PaperSheet;
use App\Models\RollLot;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    // Maximum rows allowed for export to prevent DoS
    private const MAX_EXPORT_ROWS = 10000;

    /**
     * Export roll lots or paper sheets as XLSX based on active filter mode.
     *
     * Param 'resource' menentukan data yang diexport: 'roll' (default) atau
     * 'sheet'.
     */
    public function export(Request $request)
    {
        // 15K+ rows + Excel export = butuh memory besar
        ini_set('memory_limit', '768M');

        $mode = $request->get('mode', 'advanced');
        $resource = $request->get('resource', 'roll');

        if ($resource === 'sheet') {
            $rows = $mode === 'batch' ? $this->getBatchRows($request, PaperSheet::class) : $this->getAdvancedSheetRows($request);
            
            // Check row limit
            if ($rows->count() > self::MAX_EXPORT_ROWS) {
                return response()->json([
                    'error' => 'Export terlalu besar',
                    'message' => 'Maksimal ' . self::MAX_EXPORT_ROWS . ' rows. Gunakan filter untuk mempersempit data.',
                    'total_rows' => $rows->count(),
                    'max_allowed' => self::MAX_EXPORT_ROWS,
                ], 413);
            }
            
            $export = new SheetsExport($rows);
            $filename = 'paper_sheets_' . now()->format('Ymd_His') . '.xlsx';
        } else {
            $rows = $mode === 'batch' ? $this->getBatchRows($request, RollLot::class) : $this->getAdvancedRollRows($request);
            
            // Check row limit
            if ($rows->count() > self::MAX_EXPORT_ROWS) {
                return response()->json([
                    'error' => 'Export terlalu besar',
                    'message' => 'Maksimal ' . self::MAX_EXPORT_ROWS . ' rows. Gunakan filter untuk mempersempit data.',
                    'total_rows' => $rows->count(),
                    'max_allowed' => self::MAX_EXPORT_ROWS,
                ], 413);
            }
            
            $export = new RollLotsExport($rows);
            $filename = 'roll_lots_' . now()->format('Ymd_His') . '.xlsx';
        }

        return Excel::download($export, $filename);
    }

    protected function getBatchRows(Request $request, string $modelClass)
    {
        $input = $request->get('lot_ids', '');
        $lotIds = preg_split('/[\s,;]+/', trim($input));
        $lotIds = array_filter(array_map('trim', $lotIds));
        $lotIds = array_unique(array_map('strtoupper', $lotIds));

        // Limit batch input to prevent DoS
        $lotIds = array_slice($lotIds, 0, 1000);

        return $modelClass::searchByLotIds($lotIds)->get();
    }

    protected function getAdvancedRollRows(Request $request)
    {
        $filters = $request->only(['item_id', 'grade', 'papertype', 'gramature', 'width', 'date_from', 'date_to', 'lot_id']);

        $query = RollLot::query();

        if (!empty($filters['item_id'])) $query->where('item_id', $filters['item_id']);
        if (!empty($filters['grade'])) $query->where('grade', $filters['grade']);
        if (!empty($filters['papertype'])) $query->where('papertype', 'like', '%' . $filters['papertype'] . '%');
        if (!empty($filters['gramature'])) $query->where('gramature', 'like', '%' . $filters['gramature'] . '%');
        if (!empty($filters['width'])) $query->where('width', 'like', '%' . $filters['width'] . '%');
        if (!empty($filters['date_from'])) $query->whereDate('source_tr_date', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('source_tr_date', '<=', $filters['date_to']);
        if (!empty($filters['lot_id'])) $query->where('lot_id', 'like', '%' . $filters['lot_id'] . '%');

        // Limit advanced query to prevent DoS
        return $query->orderBy('lot_id')->limit(self::MAX_EXPORT_ROWS + 1)->get();
    }

    protected function getAdvancedSheetRows(Request $request)
    {
        $filters = $request->only(['item_id', 'papertype', 'gramature', 'dimension', 'date_from', 'date_to', 'lot_id']);

        $query = PaperSheet::query();

        if (!empty($filters['item_id'])) $query->where('item_id', $filters['item_id']);
        if (!empty($filters['papertype'])) $query->where('papertype', 'like', '%' . $filters['papertype'] . '%');
        if (!empty($filters['gramature'])) $query->where('gramature', 'like', '%' . $filters['gramature'] . '%');
        if (!empty($filters['dimension'])) $query->where('dimension', 'like', '%' . $filters['dimension'] . '%');
        if (!empty($filters['date_from'])) $query->whereDate('source_tr_date', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('source_tr_date', '<=', $filters['date_to']);
        if (!empty($filters['lot_id'])) $query->where('lot_id', 'like', '%' . $filters['lot_id'] . '%');

        // Limit advanced query to prevent DoS
        return $query->orderBy('lot_id')->limit(self::MAX_EXPORT_ROWS + 1)->get();
    }
}
