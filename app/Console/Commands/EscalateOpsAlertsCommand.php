<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\OpsAlert;
use App\Models\OpsOncallShift;
use App\Services\AdminNotifier;
use App\Services\OpsWebhookNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('ops:escalate {--minutes=}')]
#[Description('按 L1/L2 值班表升级超时告警并恢复到期静默')]
class EscalateOpsAlertsCommand extends Command
{
    public function handle(AdminNotifier $adminNotifier, OpsWebhookNotifier $webhook): int
    {
        $reopened = OpsAlert::query()
            ->where('status', 'silenced')
            ->where('silenced_until', '<=', now())
            ->update([
                'status' => 'open',
                'silenced_until' => null,
                'notified_at' => null,
            ]);

        $minutes = max(1, (int) ($this->option('minutes') ?: config('deviy.ops_escalate_minutes', 15)));
        $cutoff = now()->subMinutes($minutes);
        $escalated = 0;

        $alerts = OpsAlert::query()
            ->where('status', 'open')
            ->where('escalation_level', '<', 2)
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($levelOne) use ($cutoff): void {
                    $levelOne->where('escalation_level', 0)->where('created_at', '<', $cutoff);
                })->orWhere(function ($levelTwo) use ($cutoff): void {
                    $levelTwo->where('escalation_level', 1)->where('escalated_at', '<', $cutoff);
                });
            })
            ->get();

        foreach ($alerts as $alert) {
            $level = (int) $alert->escalation_level + 1;
            $alert->update([
                'severity' => 'critical',
                'escalation_level' => $level,
                'escalated_at' => now(),
                'notified_at' => now(),
            ]);

            $admins = $this->onCallAdmins($level);
            $adminNotifier->notify(
                $admins,
                'ops_escalation',
                'L'.$level.' 告警超时未认领：'.$alert->title,
                $alert->body.'（已升级到 L'.$level.'）',
                ['alert_id' => $alert->id, 'key' => $alert->key, 'level' => $level],
                'ops_escalation:'.$alert->id.':'.$level,
            );
            $webhook->send($alert);
            $escalated++;
        }

        $this->info("告警升级完成：升级 {$escalated} 条，恢复静默 {$reopened} 条。");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Admin>
     */
    private function onCallAdmins(int $level): Collection
    {
        $shift = OpsOncallShift::query()
            ->with('admin')
            ->current($level)
            ->latest('starts_at')
            ->first();

        if ($shift?->admin?->is_active) {
            return collect([$shift->admin]);
        }

        $onCallId = (int) config('deviy.ops_oncall_admin_id', 0);
        if ($onCallId > 0) {
            return Admin::query()->whereKey($onCallId)->where('is_active', true)->get();
        }

        return Admin::query()->where('role', 'super_admin')->where('is_active', true)->get();
    }
}
