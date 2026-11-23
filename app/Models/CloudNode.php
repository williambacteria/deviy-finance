<?php

namespace App\Models;

use Database\Factories\CloudNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'agent_node_id',
    'agent_url',
    'agent_token',
    'agent_mode',
    'cert_fingerprint',
    'cert_expires_at',
    'diagnostics',
    'diagnosed_at',
    'provider',
    'host',
    'port',
    'region',
    'status',
    'network_policy',
    'agent_version',
    'capacity',
    'last_heartbeat_at',
    'is_maintenance',
])]
#[Hidden(['agent_token'])]
class CloudNode extends Model
{
    /** @use HasFactory<CloudNodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'capacity' => 'array',
            'last_heartbeat_at' => 'datetime',
            'is_maintenance' => 'boolean',
            'port' => 'integer',
            'agent_token' => 'encrypted',
            'cert_expires_at' => 'datetime',
            'diagnostics' => 'array',
            'diagnosed_at' => 'datetime',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'node_id');
    }

    public function ipPools(): HasMany
    {
        return $this->hasMany(IpPool::class, 'node_id');
    }
}
