<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RollLot extends Model
{
    protected $fillable = [
        'lot_id',
        'item_id',
        'weight',
        'papertype',
        'gramature',
        'playbond',
        'width',
        'rew_id',
        'grade',
        'comments',
        'diameter',
        'thickness',
        'description_raw',
        'source_tr_date',
        'source_tr_time',
        'import_batch_id',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'diameter' => 'string',
        'source_tr_date' => 'date',
        'source_tr_time' => 'datetime',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function scopeSearchByLotIds($query, array $lotIds)
    {
        return $query->whereIn('lot_id', $lotIds);
    }

    public function scopeFilterAdvanced($query, array $filters)
    {
        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }
        if (!empty($filters['papertype'])) {
            $query->where('papertype', 'like', '%' . addslashes($filters['papertype']) . '%');
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

        return $query;
    }
}
