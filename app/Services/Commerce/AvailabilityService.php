<?php

namespace App\Services\Commerce;

use App\Models\CloudNode;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Service;

class AvailabilityService
{
    /**
     * @return array{
     *     status: string,
     *     node: ?CloudNode,
     *     checks: array<int, array{name: string, status: string, message: string}>,
     *     errors: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    public function check(Product $product, int $quantity = 1, ?string $cycle = null): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];
        $node = null;

        if ($product->status !== 'active' || data_get($product->metadata, 'legacy_import.hidden', false)) {
            $errors[] = '商品已下架。';
            $checks[] = ['name' => 'product', 'status' => 'failed', 'message' => '商品已下架'];
        } elseif ($product->stock < $quantity) {
            $errors[] = '库存不足，剩余 '.$product->stock.' 台。';
            $checks[] = ['name' => 'stock', 'status' => 'failed', 'message' => '库存不足：剩余 '.$product->stock];
        } else {
            $checks[] = ['name' => 'stock', 'status' => 'ok', 'message' => '库存充足：剩余 '.$product->stock];
        }

        if ($cycle !== null) {
            $hasPrice = ProductPrice::query()
                ->where('product_id', $product->id)
                ->where('billing_cycle', $cycle)
                ->where('is_active', true)
                ->exists();
            if (! $hasPrice) {
                $errors[] = '所选周期暂不可购买。';
                $checks[] = ['name' => 'price', 'status' => 'failed', 'message' => '周期价格不存在：'.$cycle];
            } else {
                $checks[] = ['name' => 'price', 'status' => 'ok', 'message' => '周期价格有效：'.$cycle];
            }
        }

        if ($product->upstream_source === 'mofang') {
            $checks[] = ['name' => 'upstream', 'status' => 'ok', 'message' => '上游商品，由魔方履约'];

            return [
                'status' => $errors === [] ? 'ok' : 'failed',
                'node' => null,
                'checks' => $checks,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $node = CloudNode::query()
            ->where('status', 'online')
            ->where('is_maintenance', false)
            ->where('region', $product->region)
            ->first();

        if (! $node) {
            $node = CloudNode::query()
                ->where('status', 'online')
                ->where('is_maintenance', false)
                ->first();
            if ($node) {
                $warnings[] = '目标区域无可用节点，将调度到 '.$node->name.'。';
                $checks[] = ['name' => 'region', 'status' => 'warning', 'message' => '区域无节点，回退到 '.$node->name];
            }
        } elseif ($product->region) {
            $checks[] = ['name' => 'region', 'status' => 'ok', 'message' => '区域节点可用：'.$node->name];
        }

        if (! $node) {
            $errors[] = '当前没有在线且未维护的节点。';
            $checks[] = ['name' => 'node', 'status' => 'failed', 'message' => '没有可用节点'];
        } else {
            $checks[] = ['name' => 'node', 'status' => 'ok', 'message' => '节点可用：'.$node->name];

            $capacity = $node->capacity ?? [];
            if ($capacity === []) {
                $warnings[] = '节点未上报容量，无法校验余量。';
                $checks[] = ['name' => 'capacity', 'status' => 'warning', 'message' => '节点容量未上报'];
            } else {
                $specs = $product->specs ?? [];
                $cpuNeed = (int) ($specs['cpu'] ?? 2) * $quantity;
                $memoryNeed = (int) ($specs['memory_gb'] ?? 4) * $quantity;
                $diskNeed = (int) ($specs['disk_gb'] ?? 40) * $quantity;

                $used = Service::query()
                    ->where('node_id', $node->id)
                    ->whereIn('status', ['active', 'provisioning'])
                    ->get(['specs']);
                $cpuUsed = $used->sum(fn (Service $service): int => (int) ($service->specs['cpu'] ?? 0));
                $memoryUsed = $used->sum(fn (Service $service): int => (int) ($service->specs['memory_gb'] ?? 0));
                $diskUsed = $used->sum(fn (Service $service): int => (int) ($service->specs['disk_gb'] ?? 0));

                foreach ([
                    ['cpu', (int) ($capacity['cpu'] ?? 0), $cpuUsed, $cpuNeed, '核'],
                    ['memory', (int) ($capacity['memory_gb'] ?? 0), $memoryUsed, $memoryNeed, 'GB'],
                    ['storage', (int) ($capacity['storage_gb'] ?? 0), $diskUsed, $diskNeed, 'GB'],
                ] as [$key, $total, $usedNow, $need, $unit]) {
                    if ($total <= 0) {
                        $warnings[] = '节点未配置 '.$key.' 容量。';
                        $checks[] = ['name' => 'capacity.'.$key, 'status' => 'warning', 'message' => '容量未配置'];

                        continue;
                    }
                    if ($usedNow + $need > $total) {
                        $errors[] = '节点 '.$key.' 余量不足（可用 '.max(0, $total - $usedNow).' '.$unit.'，需要 '.$need.' '.$unit.'）。';
                        $checks[] = ['name' => 'capacity.'.$key, 'status' => 'failed', 'message' => '余量不足：'.($usedNow + $need).'/'.$total.' '.$unit];
                    } else {
                        $checks[] = ['name' => 'capacity.'.$key, 'status' => 'ok', 'message' => '可用 '.($total - $usedNow).' / '.$total.' '.$unit];
                    }
                }
            }

            $poolType = $product->type === 'nat_vm' ? 'nat' : 'dedicated';
            $pool = $node->ipPools()->where('type', $poolType)->whereColumn('used', '<', 'total')->first();
            if ($pool) {
                $checks[] = ['name' => 'ip_pool', 'status' => 'ok', 'message' => 'IP 池可用：'.$pool->name.'（剩余 '.($pool->total - $pool->used).'）'];
            } else {
                $warnings[] = '节点尚未配置可用的 '.$poolType.' IP 池，开通时将进入人工分配。';
                $checks[] = ['name' => 'ip_pool', 'status' => 'warning', 'message' => '未找到可用 '.$poolType.' IP 池'];
            }
        }

        $status = 'ok';
        if ($errors !== []) {
            $status = 'failed';
        } elseif ($warnings !== []) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'node' => $node,
            'checks' => $checks,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
