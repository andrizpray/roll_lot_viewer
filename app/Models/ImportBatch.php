<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'filename',
        'type',
        'status',
        'total_rows',
        'success_count',
        'failed_count',
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

    public function paperSheets(): HasMany
    {
        return $this->hasMany(PaperSheet::class);
    }
}
