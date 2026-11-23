<?php

namespace App\Console\Commands;

use App\Models\NetworkDriftCheck;
use App\Models\ProvisionTask;
use App\Models\ReconciliationRun;
use App\Models\SettingChangeRequest;
use App\Services\OpsAlertService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ops:scan')]
#[Description('扫描网络、财务、开通与审批异常并生成运营告警')]
class ScanOpsAlertsCommand extends Command
{
    public function handle(OpsAlertService $alerts): int
    {
        $created = 0;

        $networkKeys = [];
        foreach (NetworkDriftCheck::query()->with('node')->whereIn('status', ['drift', 'error'])->get() as $check) {
            $key = 'network:'.$check->node_id;
            $networkKeys[] = $key;
            $alerts->raise(
                $key,
                'network',
                $check->status === 'drift' ? 'critical' : 'warning',
                '节点网络异常：'.($check->node?->name ?? '#'.$check->node_id),
                $check->status === 'drift'
                    ? 'NAT 规则漂移，缺失 '.count($check->missing ?? []).' 条，多余 '.count($check->extra ?? []).' 条。'
                    : 'Agent 不可达：'.($check->last_error ?? '未返回错误'),
                ['node_id' => $check->node_id, 'status' => $check->status],
            );
            $created++;
        }
        $alerts->resolveSourceExcept('network', $networkKeys);

        $reconciliation = ReconciliationRun::query()->latest('run_date')->first();
        $reconciliationKeys = [];
        if ($reconciliation && $reconciliation->status !== 'passed') {
            $key = 'finance:reconciliation';
            $reconciliationKeys[] = $key;
            $alerts->raise($key, 'finance', 'critical', '财务对账异常', '对账发现 '.$reconciliation->issues_count.' 个问题。', [
                'run_id' => $reconciliation->id,
                'issues' => $reconciliation->issues_count,
            ]);
            $created++;
        }
        $alerts->resolveSourceExcept('finance', $reconciliationKeys);

        $provisionKeys = [];
        foreach (ProvisionTask::query()->where('status', 'failed')->latest()->limit(20)->get() as $task) {
            $key = 'provision:'.$task->id;
            $provisionKeys[] = $key;
            $alerts->raise($key, 'provision', 'critical', '开通任务失败', '任务 #'.$task->id.' '.$task->operation.' 失败：'.($task->last_error ?? '未知错误'), [
                'task_id' => $task->id,
                'service_id' => $task->service_id,
            ]);
            $created++;
        }
        $alerts->resolveSourceExcept('provision', $provisionKeys);

        $settingKeys = [];
        foreach (SettingChangeRequest::query()->where('status', 'pending')->where('created_at', '<', now()->subDay())->get() as $change) {
            $key = 'settings:'.$change->id;
            $settingKeys[] = $key;
            $alerts->raise($key, 'settings', 'warning', '设置变更等待审批', $change->group_name.'.'.$change->key.' 已等待超过 24 小时。', [
                'change_id' => $change->id,
            ]);
            $created++;
        }
        $alerts->resolveSourceExcept('settings', $settingKeys);

        $this->info("运营告警扫描完成：处理 {$created} 条信号。");

        return self::SUCCESS;
    }
}
