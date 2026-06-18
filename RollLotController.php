<?php

namespace App\Http\Controllers;

use App\Models\RollLot;
use Illuminate\Http\Request;

class RollLotController extends Controller
{
    /**
     * List roll lots with two filter modes:
     * Mode A: batch LotID search (mutually exclusive)
     * Mode B: advanced filters
     */
    public function index(Request $request)
    {
        $mode = $request->get('mode', 'advanced'); // 'batch' or 'advanced'

        if ($mode === 'batch') {
            return $this->batchSearch($request);
        }

        return $this->advancedFilter($request);
    }

    /**
     * Mode A - Batch LotID search.
     */
    protected function batchSearch(Request $request)
    {
        $input = $request->get('lot_ids', '');

        // Parse lot IDs from input (supports newline, comma, semicolon, tab, space)
        $lotIds = preg_split('/[\s,;]+/', trim($input));
        $lotIds = array_filter(array_map('trim', $lotIds));
        $lotIds = array_values(array_unique(array_map('strtoupper', $lotIds)));

        if (empty($lotIds)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'mode' => 'batch',
                    'requested_count' => 0,
                    'found_count' => 0,
                    'not_found' => [],
                ],
            ]);
        }

        // Query matching lots
        $results = RollLot::searchByLotIds($lotIds)->get();

        // Find which IDs were not found
        $foundIds = $results->pluck('lot_id')->map(fn($id) => strtoupper($id))->unique()->toArray();
        $notFound = array_values(array_diff($lotIds, $foundIds));

        return response()->json([
            'data' => $results,
            'meta' => [
                'mode' => 'batch',
                'requested_count' => count($lotIds),
                'found_count' => count($foundIds),
                'not_found' => $notFound,
                'not_found_count' => count($notFound),
            ],
        ]);
    }

    /**
     * Mode B - Advanced filter with pagination.
     */
    protected function advancedFilter(Request $request)
    {
        $filters = $request->only(['item_id', 'papertype', 'grade', 'date_from', 'date_to', 'gramature', 'width', 'lot_id']);

        $query = RollLot::query();

        // Apply filters
        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }
        if (!empty($filters['papertype'])) {
            $query->where('papertype', 'like', '%' . $filters['papertype'] . '%');
        }
        if (!empty($filters['grade'])) {
            $query->where('grade', $filters['grade']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('source_tr_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('source_tr_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['gramature'])) {
            $query->where('gramature', 'like', '%' . $filters['gramature'] . '%');
        }
        if (!empty($filters['width'])) {
            $query->where('width', $filters['width']);
        }
        if (!empty($filters['lot_id'])) {
            $query->where('lot_id', 'like', '%' . $filters['lot_id'] . '%');
        }

        $results = $query->orderBy('lot_id')->paginate($request->get('per_page', 50));

        return response()->json($results);
    }

    /**
     * Get single roll lot detail.
     */
    public function show($id)
    {
        $rollLot = RollLot::findOrFail($id);

        return response()->json($rollLot);
    }
}
