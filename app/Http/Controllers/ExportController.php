<?php

namespace App\Http\Controllers;

use App\Exports\RollLotsExport;
use App\Exports\SheetsExport;
use App\Models\PaperSheet;
use App\Models\RollLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    private const MAX_EXPORT_ROWS = 10000;

    public function export(Request $request)
    {
        try {
            ini_set('memory_limit', '1024M');

            $mode = $request->get('mode', 'advanced');
            $resource = $request->get('resource', 'roll');

            if ($resource === 'sheet') {
                $rows = $mode === 'batch' ? $this->getBatchRows($request, PaperSheet::class) : $this->getAdvancedSheetRows($request);
                $export = new SheetsExport($rows);
            } else {
                $rows = $mode === 'batch' ? $this->getBatchRows($request, RollLot::class) : $this->getAdvancedRollRows($request);
                $export = new RollLotsExport($rows);
            }

            $filename = ($resource === 'sheet' ? 'paper_sheets_' : 'roll_lots_') . now()->format('Ymd_His') . '.xlsx';

            Excel::store($export, $filename, 'public');

            $fullPath = Storage::disk('public')->path($filename);

            if (!file_exists($fullPath)) {
                throw new \Exception('Export file not found at ' . $fullPath);
            }

            return response()->download($fullPath, $filename)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Export failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function getBatchRows(Request $request, string $modelClass)
    {
        $input = $request->get('lot_ids', '');
        $lotIds = preg_split('/[\s,;]+/', trim($input));
        $lotIds = array_filter(array_map('trim', $lotIds));
        $lotIds = array_unique(array_map('strtoupper', $lotIds));
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

        return $query->orderBy('lot_id')->limit(self::MAX_EXPORT_ROWS + 1)->get();
    }
}