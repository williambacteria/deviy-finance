<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'node_id',
    'status',
    'action',
    'repair_status',
    'repair_result',
    'repaired_at',
    'notified_at',
    'expected_count',
    'actual_count',
    'missing',
    'extra',
    'last_error',
    'checked_at',
])]
class NetworkDriftCheck extends Model
{
    protected function casts(): array
    {
        return [
            'expected_count' => 'integer',
            'actual_count' => 'integer',
            'missing' => 'array',
            'extra' => 'array',
            'repair_result' => 'array',
            'repaired_at' => 'datetime',
            'notified_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(CloudNode::class, 'node_id');
    }
}
