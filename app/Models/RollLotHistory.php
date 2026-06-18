<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RollLotHistory extends Model
{
    protected $table = 'roll_lot_histories';

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
        'archived_at',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'diameter' => 'decimal:2',
        'source_tr_date' => 'date',
        'source_tr_time' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
