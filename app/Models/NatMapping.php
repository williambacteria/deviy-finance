<?php

namespace App\Models;

use Database\Factories\NatMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_id',
    'external_ip',
    'external_port',
    'internal_ip',
    'internal_port',
    'protocol',
    'status',
])]
class NatMapping extends Model
{
    /** @use HasFactory<NatMappingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'external_port' => 'integer',
            'internal_port' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
