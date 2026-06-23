<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    protected $table = 'import_jobs';

    protected $fillable = [
        'filename',
        'type',
        'status',
        'total_rows',
        'success_count',
        'failed_count',
        'error_message',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
    ];
}
