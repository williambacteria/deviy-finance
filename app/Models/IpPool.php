<?php

namespace App\Models;

use Database\Factories\IpPoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['node_id', 'name', 'cidr', 'type', 'gateway', 'vlan', 'total', 'used'])]
class IpPool extends Model
{
    /** @use HasFactory<IpPoolFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'used' => 'integer',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(CloudNode::class, 'node_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(IpAddress::class, 'pool_id');
    }
}
