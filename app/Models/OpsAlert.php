<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'key',
    'source',
    'severity',
    'escalation_level',
    'title',
    'body',
    'data',
    'status',
    'assigned_admin_id',
    'occurrences',
    'acknowledged_at',
    'escalated_at',
    'notified_at',
    'silenced_until',
    'resolved_at',
    'last_seen_at',
])]
class OpsAlert extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'occurrences' => 'integer',
            'escalation_level' => 'integer',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'notified_at' => 'datetime',
            'silenced_until' => 'datetime',
            'resolved_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved'], true);
    }
}
