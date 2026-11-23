<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'notice_id',
    'channel',
    'recipient',
    'template_key',
    'status',
    'attempts',
    'next_retry_at',
    'error',
    'payload',
    'sent_at',
])]
class NotificationLog extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }
}
