<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'admin_id',
    'group_name',
    'key',
    'old_value',
    'new_value',
    'status',
    'reviewed_by',
    'reviewed_at',
    'reason',
])]
class SettingChangeRequest extends Model
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SettingChangeApproval::class, 'setting_change_request_id');
    }
}
