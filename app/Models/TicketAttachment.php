<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'ticket_reply_id',
    'user_id',
    'admin_id',
    'disk',
    'path',
    'original_name',
    'mime',
    'size',
])]
class TicketAttachment extends Model
{
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'ticket_reply_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function humanSize(): string
    {
        if ($this->size >= 1024 * 1024) {
            return round($this->size / 1024 / 1024, 1).' MB';
        }

        return max(1, (int) round($this->size / 1024)).' KB';
    }
}
