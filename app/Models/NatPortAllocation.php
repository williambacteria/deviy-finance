<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_id', 'external_ip', 'port_start', 'port_end', 'status'])]
class NatPortAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'port_start' => 'integer',
            'port_end' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
