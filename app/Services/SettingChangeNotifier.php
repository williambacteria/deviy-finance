<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\SettingChangeRequest;
use Illuminate\Support\Facades\Mail;

class SettingChangeNotifier
{
    public function notify(SettingChangeRequest $change, string $subject): void
    {
        $recipients = Admin::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->pluck('email')
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);

        if ($recipients->isEmpty()) {
            return;
        }

        Mail::raw(
            "Deviy Cloud 设置变更审批\n\n设置项：{$change->group_name}.{$change->key}\n原值：{$change->old_value}\n新值：{$change->new_value}\n提交人：".($change->admin?->name ?? '系统')."\n\n请登录后台 /admin/settings/changes 处理。",
            function ($message) use ($recipients, $subject): void {
                $message->to($recipients->all())->subject($subject);
            },
        );
    }
}
