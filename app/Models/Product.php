<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_group_id',
    'name',
    'code',
    'type',
    'region',
    'short_description',
    'description',
    'stock',
    'status',
    'sort_order',
    'upstream_source',
    'upstream_product_id',
    'metadata',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'stock' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function scopeCatalogVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('metadata->legacy_import->hidden')
                ->orWhere('metadata->legacy_import->hidden', false);
        });
    }

    public function isPurchasable(): bool
    {
        return $this->type !== 'colocation' && $this->status === 'active' && $this->stock > 0 && ! data_get($this->metadata, 'legacy_import.hidden', false);
    }
}
