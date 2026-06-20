<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperSheet extends Model
{
    protected $fillable = [
        'lot_id',
        'item_id',
        'weight',
        'papertype',
        'gramature',
        'dimension',
        'content_pack',
        'content_pallet',
        'description_raw',
        'source_tr_date',
        'source_tr_time',
        'import_batch_id',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'content_pack' => 'integer',
        'content_pallet' => 'integer',
        'source_tr_date' => 'date',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function scopeSearchByLotIds($query, array $lotIds)
    {
        return $query->whereIn('lot_id', $lotIds);
    }
}
