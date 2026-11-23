<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'original_name',
    'mime',
    'size',
    'disk',
    'path',
    'scanner',
    'reason',
    'ip_address',
])]
class QuarantineFile extends Model
{
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function humanSize(): string
    {
        if ($this->size >= 1024 * 1024) {
            return round($this->size / 1024 / 1024, 1).' MB';
        }

        return max(1, (int) round($this->size / 1024)).' KB';
    }
}
