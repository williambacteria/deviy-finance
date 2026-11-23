<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\Provisioning\MetricCollector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('services:metrics {--limit=300}')]
#[Description('采集本地云服务器指标用于资源监控')]
class CollectServiceMetricsCommand extends Command
{
    public function handle(MetricCollector $collector): int
    {
        $services = Service::query()
            ->with(['product', 'node'])
            ->where('status', 'active')
            ->whereNotNull('external_ref')
            ->whereHas('product', fn ($query) => $query->where('upstream_source', '!=', 'mofang')->orWhereNull('upstream_source'))
            ->oldest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $collected = 0;
        $failed = 0;

        foreach ($services as $service) {
            try {
                if ($service->product?->upstream_source === 'local' && $service->product?->type === 'nat_vm') {
                    app(\App\Services\Provisioning\ServiceNetworkSync::class)->sync($service,true);
                }
                if ($collector->collectService($service) !== null) {
                    $collected++;
                }
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
            }
        }

        $this->info('本地实例指标采集完成：成功 '.$collected.'，失败 '.$failed);

        return self::SUCCESS;
    }
}
