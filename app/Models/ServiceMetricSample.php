<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_id',
    'recorded_at',
    'cpu_percent',
    'memory_used_gb',
    'disk_read_mbps',
    'disk_write_mbps',
    'net_rx_mbps',
    'net_tx_mbps',
    'meta',
])]
class ServiceMetricSample extends Model
{
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'cpu_percent' => 'float',
            'memory_used_gb' => 'float',
            'disk_read_mbps' => 'float',
            'disk_write_mbps' => 'float',
            'net_rx_mbps' => 'float',
            'net_tx_mbps' => 'float',
            'meta' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
