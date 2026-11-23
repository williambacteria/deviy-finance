<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'service_no',
    'user_id',
    'product_id',
    'order_id',
    'node_id',
    'name',
    'status',
    'billing_cycle',
    'auto_renew',
    'region',
    'ip_address',
    'nat_ip',
    'nat_port_start',
    'nat_port_end',
    'bandwidth_mbps',
    'os',
    'specs',
    'expires_at',
    'last_renewed_at',
    'grace_ends_at',
    'next_due_at',
    'provision_status',
    'external_ref',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'auto_renew' => 'boolean',
            'expires_at' => 'datetime',
            'last_renewed_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'next_due_at' => 'datetime',
            'bandwidth_mbps' => 'integer',
            'nat_port_start' => 'integer',
            'nat_port_end' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(CloudNode::class, 'node_id');
    }

    public function natMappings(): HasMany
    {
        return $this->hasMany(NatMapping::class);
    }

    public function provisionTasks(): HasMany
    {
        return $this->hasMany(ProvisionTask::class);
    }

    public function upstreamOrder(): HasOne
    {
        return $this->hasOne(UpstreamOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
