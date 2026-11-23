<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(?Admin $admin, string $action, string $description, ?Model $subject = null, array $meta = []): void
    {
        AuditLog::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'ip_address' => request()->ip(),
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
