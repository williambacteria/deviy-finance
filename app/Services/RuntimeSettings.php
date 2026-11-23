<?php

namespace App\Services;

use App\Models\Setting;

class RuntimeSettings
{
    public function apply(): void
    {
        try {
            $values = Setting::query()->whereIn('group_name', ['billing', 'attachment'])->pluck('value', 'key');
        } catch (\Throwable) {
            return;
        }
        foreach (['grace_days' => ['deviy.grace_days', 0, 30], 'retention_days' => ['deviy.retention_days', 1, 90]] as $key => [$target, $minimum, $maximum]) {
            if ($values->has($key) && filter_var($values[$key], FILTER_VALIDATE_INT) !== false) {
                config([$target => min($maximum, max($minimum, (int) $values[$key]))]);
            }
        }
        if (is_numeric($values['upload_max_mb'] ?? null)) {
            config(['deviy.upload_max_bytes' => min(20, max(1, (int) $values['upload_max_mb'])) * 1024 * 1024]);
        }
        foreach (['quota_standard_mb' => 'standard', 'quota_vip_mb' => 'vip'] as $key => $group) {
            if (is_numeric($values[$key] ?? null) && (int) $values[$key] > 0) {
                config(['deviy.attachment_quota_groups.'.$group => (int) $values[$key] * 1024 * 1024]);
                if ($group === 'standard') {
                    config(['deviy.ticket_attachment_quota' => (int) $values[$key] * 1024 * 1024]);
                }
            }
        }
    }
}
