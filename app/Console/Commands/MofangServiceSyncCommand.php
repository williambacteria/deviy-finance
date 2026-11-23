<?php

namespace App\Console\Commands;

use App\Models\UpstreamOrder;
use App\Services\Mofang\Contracts\MofangGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('mofang:sync-services {--limit=50}')]
#[Description('同步魔方上游实例状态到本地服务')]
class MofangServiceSyncCommand extends Command
{
    public function handle(MofangGateway $gateway): int
    {
        $orders = UpstreamOrder::query()
            ->with('service')
            ->whereNotNull('upstream_service_id')->where('type','purchase')
            ->whereIn('status', ['paid', 'provisioning', 'active', 'suspended'])
            ->orderBy('last_synced_at')->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($orders as $upstreamOrder) {
            try {
                $detail = $gateway->fetchService((string) $upstreamOrder->upstream_service_id);
                $host = (array) data_get($detail, 'data.host_data', []);
                $remoteStatus = strtolower((string) (
                    $host['domainstatus']
                    ?? data_get($detail, 'data.domainstatus')
                    ?? data_get($detail, 'domainstatus')
                    ?? $detail['status']
                    ?? ''
                ));
                $localStatus = match ($remoteStatus) {
                    'active', 'running' => 'active',
                    'suspended' => 'suspended',
                    'stopped' => 'stopped',
                    'terminated', 'deleted', 'cancelled' => 'terminated',
                    'provisioning', 'pending', 'creating' => 'provisioning',
                    default => null,
                };

                if ($localStatus === null) {
                    $upstreamOrder->update(['last_error' => '未知上游状态：'.$remoteStatus, 'last_synced_at' => now()]);
                    $failed++;

                    continue;
                }

                if ($upstreamOrder->service) {
                    app(\App\Services\Mofang\MofangServiceSync::class)->sync($upstreamOrder->service, $detail);
                }

                $upstreamOrder->service?->update([
                    'status' => $localStatus,
                    'provision_status' => $localStatus === 'active' ? 'success' : 'upstream_'.$localStatus,
                ]);

                $upstreamOrder->update([
                    'status' => $localStatus === 'active' ? 'active' : $localStatus,
                    'last_error' => null,
                    'last_synced_at' => now(),
                ]);
                $updated++;
            } catch (Throwable $exception) {
                $upstreamOrder->update([
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                    'last_synced_at' => now(),
                ]);
                $failed++;
                $this->warn("上游实例 #{$upstreamOrder->id} 同步失败：{$exception->getMessage()}");
            }
        }

        $this->info("魔方实例同步完成：更新 {$updated}，失败 {$failed}");

        return self::SUCCESS;
    }
}
