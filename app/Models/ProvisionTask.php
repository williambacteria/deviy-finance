<?php

namespace App\Models;

use Database\Factories\ProvisionTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_id',
    'operation',
    'status',
    'payload',
    'result',
    'attempts',
    'last_error',
    'dispatched_at',
    'completed_at',
])]
class ProvisionTask extends Model
{
    /** @use HasFactory<ProvisionTaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'attempts' => 'integer',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
