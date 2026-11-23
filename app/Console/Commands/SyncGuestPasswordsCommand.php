<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\Provisioning\ControllerGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('services:guest-passwords {--limit=100}')]
#[Description('为已开通的 Windows 实例同步登录密码')]
class SyncGuestPasswordsCommand extends Command
{
    public function handle(ControllerGateway $gateway): int
    {
        $services = Service::query()
            ->with(['product', 'node', 'natMappings'])
            ->where('status', 'active')
            ->whereNotNull('external_ref')
            ->where('os', 'like', '%windows%')
            ->whereHas('product', fn ($query) => $query->whereNotIn('upstream_source', ['mofang', 'pterodactyl'])->orWhereNull('upstream_source'))
            ->oldest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($services as $service) {
            $specs = is_array($service->specs) ? $service->specs : [];
            $remote = is_array($specs['remote'] ?? null) ? $specs['remote'] : [];
            if (! empty($remote['password_applied_at']) || empty($remote['password'])) {
                continue;
            }

            $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
            $ref = trim((string) $service->external_ref);
            $vmid = str_starts_with($ref, 'vmid:') ? substr($ref, 5) : (str_starts_with($ref, 'id:') ? substr($ref, 3) : $ref);
            $internalIp = (string) (data_get($specs, 'internal_ip') ?: $service->natMappings->first()?->internal_ip);
            if ($nodeId === '' || $vmid === '' || $internalIp === '') {
                continue;
            }

            try {
                $gateway->changeGuestPassword(
                    $nodeId,
                    $vmid,
                    $internalIp,
                    (string) ($remote['username'] ?? 'Administrator'),
                    (string) $remote['password'],
                );
                $remote['password_applied_at'] = now()->timestamp;
                $specs['remote'] = $remote;
                $service->update(['specs' => $specs]);
                $synced++;
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
            }
        }

        $this->info('Windows 实例密码同步完成：成功 '.$synced.'，失败 '.$failed);

        return self::SUCCESS;
    }
}
