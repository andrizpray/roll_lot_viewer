<?php

namespace App\Http\Controllers;

use App\Models\PaperSheet;
use Illuminate\Http\Request;

class PaperSheetController extends Controller
{
    /**
     * List paper sheets with two filter modes:
     * Mode A: batch LotID search (mutually exclusive)
     * Mode B: advanced filters
     */
    public function index(Request $request)
    {
        $mode = $request->get('mode', 'advanced');

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

        $results = PaperSheet::searchByLotIds($lotIds)->get();

        $foundIds = $results->pluck('lot_id')->map(fn ($id) => strtoupper($id))->unique()->toArray();
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
        $filters = $request->only(['item_id', 'papertype', 'gramature', 'dimension', 'date_from', 'date_to', 'lot_id']);

        $query = PaperSheet::query();

        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }
        if (!empty($filters['papertype'])) {
            $query->where('papertype', 'like', '%' . $filters['papertype'] . '%');
        }
        if (!empty($filters['gramature'])) {
            $query->where('gramature', 'like', '%' . $filters['gramature'] . '%');
        }
        if (!empty($filters['dimension'])) {
            $query->where('dimension', 'like', '%' . $filters['dimension'] . '%');
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('source_tr_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('source_tr_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['lot_id'])) {
            $query->where('lot_id', 'like', '%' . $filters['lot_id'] . '%');
        }

        $perPage = (int) $request->get('per_page', 50);
        $perPage = $perPage > 0 ? $perPage : 50;
        $results = $query->orderBy('lot_id')->paginate($perPage);

        return response()->json($results);
    }

    /**
     * Get single paper sheet detail.
     */
    public function show($id)
    {
        $sheet = PaperSheet::findOrFail($id);

        return response()->json($sheet);
    }
}
