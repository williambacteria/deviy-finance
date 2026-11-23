<?php

namespace App\Services\Commerce;

use App\Models\CloudNode;
use App\Models\IpAddress;
use App\Models\NatPortAllocation;
use App\Models\Product;
use App\Models\Service;

class IpAllocator
{
    public function allocate(?CloudNode $node, Service $service, ?Product $product): void
    {
        if (! $node || ! $product || in_array($product->upstream_source, ['mofang', 'qzvps'], true)) {
            return;
        }

        // Controller-managed products get their IP, NAT range and DNS directly
        // from the controller so finance never disagrees with the host settings.
        if (trim((string) data_get($product->metadata, 'controller.node_id')) !== '') {
            return;
        }

        if ($product->type === 'nat_vm') {
            $this->allocateNat($node, $service);

            return;
        }

        $this->allocateDedicated($node, $service);
    }

    private function allocateDedicated(CloudNode $node, Service $service): void
    {
        $address = IpAddress::query()
            ->where('status', 'available')
            ->whereHas('pool', fn ($query) => $query->where('node_id', $node->id)->where('type', 'dedicated'))
            ->lockForUpdate()
            ->first();

        if (! $address) {
            return;
        }

        $address->update([
            'status' => 'assigned',
            'service_id' => $service->id,
            'assigned_at' => now(),
        ]);
        $address->pool?->increment('used');

        $service->update(['ip_address' => $address->address]);
    }

    private function allocateNat(CloudNode $node, Service $service): void
    {
        $portCount = max(1, min(1000, (int) ($service->specs['nat_ports'] ?? 20)));
        $address = IpAddress::query()
            ->where('status', 'available')
            ->whereHas('pool', fn ($query) => $query->where('node_id', $node->id)->where('type', 'nat'))
            ->first();

        $externalIp = $address?->address;
        if (! $externalIp) {
            return;
        }

        $used = Service::query()
            ->where('nat_ip', $externalIp)
            ->whereNotNull('nat_port_start')
            ->whereNotNull('nat_port_end')
            ->lockForUpdate()
            ->get(['nat_port_start', 'nat_port_end'])
            ->map(fn (Service $service): array => [$service->nat_port_start, $service->nat_port_end])
            ->merge(
                NatPortAllocation::query()
                    ->where('external_ip', $externalIp)
                    ->where('status', 'allocated')
                    ->lockForUpdate()
                    ->get(['port_start', 'port_end'])
                    ->map(fn (NatPortAllocation $allocation): array => [$allocation->port_start, $allocation->port_end])
            )
            ->sortBy(0)
            ->values();

        $start = 20000;
        foreach ($used as [$usedStart, $usedEnd]) {
            if ($start + ($portCount - 1) < $usedStart) {
                break;
            }
            $start = max($start, $usedEnd + 1);
        }

        if ($start + ($portCount - 1) > 65000) {
            $start = 20000;
        }

        $end = $start + $portCount - 1;

        $service->update([
            'nat_ip' => $externalIp,
            'nat_port_start' => $start,
            'nat_port_end' => $end,
        ]);

        NatPortAllocation::create([
            'service_id' => $service->id,
            'external_ip' => $externalIp,
            'port_start' => $start,
            'port_end' => $end,
            'status' => 'allocated',
        ]);
    }

    public function release(Service $service): void
    {
        $address = IpAddress::query()->where('service_id', $service->id)->first();
        if ($address) {
            $address->update(['status' => 'available', 'service_id' => null, 'assigned_at' => null]);
            $address->pool?->decrement('used');
        }

        NatPortAllocation::query()
            ->where('service_id', $service->id)
            ->where('status', 'allocated')
            ->update(['status' => 'released']);
    }
}
