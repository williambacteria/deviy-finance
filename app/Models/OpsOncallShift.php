<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_id', 'level', 'starts_at', 'ends_at', 'note'])]
class OpsOncallShift extends Model
{
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function scopeCurrent($query, int $level)
    {
        return $query->where('level', $level)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
