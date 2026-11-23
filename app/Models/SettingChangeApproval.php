<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['setting_change_request_id', 'admin_id'])]
class SettingChangeApproval extends Model
{
    public function request(): BelongsTo
    {
        return $this->belongsTo(SettingChangeRequest::class, 'setting_change_request_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
