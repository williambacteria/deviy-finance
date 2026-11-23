<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'service_id',
    'product_id',
    'source',
    'type',
    'upstream_order_id',
    'upstream_service_id',
    'status',
    'amount',
    'billing_cycle',
    'upstream_expires_at',
    'attempts',
    'last_error',
    'payload',
    'last_synced_at',
])]
class UpstreamOrder extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'attempts' => 'integer',
            'payload' => 'array',
            'upstream_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
