<?php

namespace App\Console\Commands;

use App\Models\Notice;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('services:lifecycle')]
#[Description('处理到期宽限、停机与回收')]
class ServiceLifecycleCommand extends Command
{
    public function handle(NotificationDispatcher $dispatcher): int
    {
        $graced = 0;
        $suspended = 0;
        $terminated = 0;

        $services = Service::query()
            ->with('product')
            ->whereIn('status', ['active', 'suspended'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit(500)
            ->get();

        foreach ($services as $service) {
            if (data_get($service->specs, 'admin_deletion')) { continue; }
            if (\App\Support\Colocation::service($service)) { continue; }
            if (data_get($service->specs, 'legacy_import.operation_hold', false)) {
                continue;
            }

            if ($service->product?->upstream_source === 'mofang') {
                continue;
            }

            if ($service->status === 'active' && ! $service->grace_ends_at) {
                $grace = $service->expires_at->copy()->addDays((int) config('deviy.grace_days', 3));
                $service->update(['grace_ends_at' => $grace]);

                if (now()->lessThanOrEqualTo($grace)) {
                    $graced++;

                    continue;
                }

                $service->refresh();
            }

            if ($service->status === 'active' && $service->grace_ends_at && now()->greaterThan($service->grace_ends_at)) {
                $service->update(['status' => 'suspended', 'provision_status' => 'queued']);
                ProvisionTask::create([
                    'service_id' => $service->id,
                    'operation' => 'suspend',
                    'status' => 'queued',
                    'payload' => ['source' => 'lifecycle', 'reason' => 'overdue'],
                    'attempts' => 0,
                ]);

                $notice = Notice::create([
                    'user_id' => $service->user_id,
                    'type' => 'service_suspended',
                    'title' => '实例已停机',
                    'body' => $service->name.'（'.$service->service_no.'）因到期未续费已停机。',
                    'data' => ['service_id' => $service->id],
                    'dedupe_key' => 'service_suspended:'.$service->id.':'.$service->grace_ends_at?->toDateString(),
                ]);
                $dispatcher->dispatch($notice);
                $suspended++;

                continue;
            }

            if ($service->status === 'suspended' && $service->grace_ends_at && now()->greaterThan($service->grace_ends_at->copy()->addDays((int) config('deviy.retention_days', 7)))) {
                $service->update(['status' => 'terminated', 'provision_status' => 'queued']);
                ProvisionTask::create([
                    'service_id' => $service->id,
                    'operation' => 'terminate',
                    'status' => 'queued',
                    'payload' => ['source' => 'lifecycle', 'reason' => 'retention_expired'],
                    'attempts' => 0,
                ]);
                $terminated++;
            }
        }

        $this->info("生命周期完成：宽限 {$graced}，停机 {$suspended}，回收 {$terminated}");

        return self::SUCCESS;
    }
}
