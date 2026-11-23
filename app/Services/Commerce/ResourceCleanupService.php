<?php

namespace App\Services\Commerce;

use App\Models\NatMapping;
use App\Models\ProvisionTask;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class ResourceCleanupService
{
    public function __construct(private readonly IpAllocator $ipAllocator) {}

    public function releaseForTermination(Service $service): void
    {
        $hadNat = DB::transaction(function () use ($service): bool {
            $mappings = NatMapping::query()
                ->where('service_id', $service->id)
                ->whereIn('status', ['pending', 'active'])
                ->lockForUpdate()
                ->get();

            foreach ($mappings as $mapping) {
                $mapping->update(['status' => 'released']);
            }

            $this->ipAllocator->release($service);

            return $service->nat_ip !== null || $mappings->isNotEmpty();
        });

        if ($hadNat && $service->node_id) {
            ProvisionTask::create([
                'service_id' => $service->id,
                'operation' => 'sync_nat',
                'status' => 'queued',
                'payload' => ['source' => 'cleanup', 'action' => 'terminate'],
                'attempts' => 0,
            ]);
        }
    }
}
