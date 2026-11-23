<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'channel', 'type', 'enabled'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function allows(User $user, string $channel, string $type): bool
    {
        $preference = static::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('type', $type)
            ->first();

        return $preference?->enabled ?? true;
    }
}
