<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'filename',
        'total_rows',
        'success_count',
        'failed_count',
        'status',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    public function rollLots(): HasMany
    {
        return $this->hasMany(RollLot::class);
    }

    public function rollLotHistories(): HasMany
    {
        return $this->hasMany(RollLotHistory::class);
    }
}
