<?php

namespace App\Models;

use Database\Factories\UpstreamProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source',
    'upstream_id',
    'name',
    'group_name',
    'region',
    'description',
    'prices',
    'currency',
    'status',
    'markup_percent',
    'mapped_product_id',
    'raw_payload',
    'synced_at',
])]
class UpstreamProduct extends Model
{
    /** @use HasFactory<UpstreamProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'prices' => 'array',
            'raw_payload' => 'array',
            'markup_percent' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function mappedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'mapped_product_id');
    }
}
