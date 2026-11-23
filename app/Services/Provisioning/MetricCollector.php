<?php

namespace App\Services\Provisioning;

use App\Models\Service;
use App\Models\ServiceMetricSample;
use Illuminate\Support\Carbon;

class MetricCollector
{
    public function __construct(private readonly ControllerGateway $gateway)
    {
    }

    public function collectService(Service $service): ?ServiceMetricSample
    {
        if ($service->product?->upstream_source === 'mofang') {
            return null;
        }

        $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
        $ref = trim((string) $service->external_ref);
        $vmid = str_starts_with($ref, 'vmid:') ? substr($ref, 5) : (str_starts_with($ref, 'id:') ? substr($ref, 3) : $ref);
        if ($nodeId === '' || $vmid === '') {
            return null;
        }

        $stats = $this->gateway->vmStats($nodeId, $vmid);
        if ($stats === []) {
            return null;
        }

        $now = now();
        $last = ServiceMetricSample::query()
            ->where('service_id', $service->id)
            ->latest('recorded_at')
            ->first();

        $seconds = $last ? max(1, $last->recorded_at->diffInSeconds($now)) : 0;
        $cpu = 0.0;
        if ($last && $seconds > 0) {
            $cpuDelta = max(0, (float) ($stats['cpu_time_ns'] ?? 0) - (float) data_get($last->meta ?? [], 'cpu_time_ns', 0));
            $cores = max(1, (int) ($service->specs['cpu'] ?? 1));
            $cpu = min(100, ($cpuDelta / 1_000_000_000 / $seconds / $cores) * 100);
        }

        $bytesPerSecond = function (string $key) use ($stats, $last, $seconds): float {
            if (! $last || $seconds <= 0) {
                return 0;
            }
            $previous = (float) data_get($last->meta ?? [], $key, 0);
            $delta = max(0, (float) ($stats[$key] ?? 0) - $previous);
            return $delta / $seconds;
        };

        $sample = ServiceMetricSample::query()->updateOrCreate(
            ['service_id' => $service->id, 'recorded_at' => $now],
            [
                'cpu_percent' => round($cpu, 2),
                'memory_used_gb' => round(min((float) ($stats['memory_used_mb'] ?? 0), (float) ($stats['memory_total_mb'] ?? 0)) / 1024, 3),
                'disk_read_mbps' => round($bytesPerSecond('disk_rd_bytes') / 1024 / 1024, 3),
                'disk_write_mbps' => round($bytesPerSecond('disk_wr_bytes') / 1024 / 1024, 3),
                'net_rx_mbps' => round($bytesPerSecond('net_rx_bytes') * 8 / 1024 / 1024, 3),
                'net_tx_mbps' => round($bytesPerSecond('net_tx_bytes') * 8 / 1024 / 1024, 3),
            ],
        );

        $sample->meta = [
            'cpu_time_ns' => (float) ($stats['cpu_time_ns'] ?? 0),
            'disk_rd_bytes' => (float) ($stats['disk_rd_bytes'] ?? 0),
            'disk_wr_bytes' => (float) ($stats['disk_wr_bytes'] ?? 0),
            'net_rx_bytes' => (float) ($stats['net_rx_bytes'] ?? 0),
            'net_tx_bytes' => (float) ($stats['net_tx_bytes'] ?? 0),
        ];
        $sample->save();

        ServiceMetricSample::query()
            ->where('service_id', $service->id)
            ->where('recorded_at', '<', $now->copy()->subDays(30))
            ->delete();

        return $sample;
    }
}
