<?php

namespace App\Services\Provisioning;

use App\Models\ProvisionTask;
use App\Models\Service;
use App\Services\Mail\MailSettings;
use App\Services\Pterodactyl\PterodactylProvisioner;
use App\Services\Qzvps\QzvpsProvisioner;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProvisionTaskDispatcher
{
    /**
     * @return array{dispatched: int, failed: int, skipped: int}
     */
    public function dispatch(int $limit = 20): array
    {
        $result = ['dispatched' => 0, 'failed' => 0, 'skipped' => 0];

        $tasks = ProvisionTask::query()
            ->with(['service.node'])
            ->where('status', 'queued')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($tasks as $task) {
            $locked = DB::transaction(function () use ($task): ?ProvisionTask {
                $candidate = ProvisionTask::query()->lockForUpdate()->find($task->id);

                if (! $candidate || $candidate->status !== 'queued') {
                    return null;
                }

                $candidate->update([
                    'status' => 'dispatched',
                    'attempts' => $candidate->attempts + 1,
                    'dispatched_at' => now(),
                ]);

                return $candidate;
            });

            if (! $locked) {
                $result['skipped']++;

                continue;
            }

            try {
                $this->dispatchOne($locked);
                $result['dispatched']++;
            } catch (Throwable $exception) {
                $result['failed']++;
                $this->markFailed($locked, $exception);
            }
        }

        return $result;
    }

    /**
     * Dispatch a specific task immediately so admin actions can wait for the
     * controller result before changing local service state.
     */
    public function dispatchTask(ProvisionTask $task): void
    {
        $locked = DB::transaction(function () use ($task): ?ProvisionTask {
            $candidate = ProvisionTask::query()->lockForUpdate()->find($task->id);

            if (! $candidate || $candidate->status !== 'queued') {
                return null;
            }

            $candidate->update([
                'status' => 'dispatched',
                'attempts' => $candidate->attempts + 1,
                'dispatched_at' => now(),
            ]);

            return $candidate;
        });

        if (! $locked) {
            throw new \RuntimeException('任务已被其他进程处理。');
        }

        try {
            $this->dispatchOne($locked);
        } catch (Throwable $exception) {
            $this->markFailed($locked, $exception);
            throw $exception;
        }
    }

    private function dispatchOne(ProvisionTask $task): void
    {
        $service = $task->service;

        if (! $service instanceof Service) {
            throw new \RuntimeException('任务没有关联服务。');
        }

        if (data_get($service->specs, 'legacy_import.operation_hold', false) || (data_get($service->specs, 'legacy_import.source') === 'www.deviy.cn' && in_array($service->status, ['terminated', 'cancelled'], true))) {
            throw new \RuntimeException('该迁入实例正在核查或已结束，暂不可执行运维任务。');
        }

        if ($service->product?->upstream_source === 'pterodactyl') {
            app(PterodactylProvisioner::class)->dispatch($task, $service);

            return;
        }

        if ($service->product?->upstream_source === 'qzvps') {
            app(QzvpsProvisioner::class)->dispatch($task, $service);

            return;
        }

        $node = $service->node;

        if (! $node || ! $node->agent_node_id) {
            throw new \RuntimeException('服务节点未配置 Agent Node ID。');
        }

        $nodeId = $node->agent_node_id;
        $payload = $this->requestPayload($task, $service);

        $response = $this->client()
            ->post("/api/nodes/{$nodeId}".$this->pathFor($task, $service), $payload)
            ->throw();

        $body = $response->json();
        $this->applyResult($task, $service, $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(ProvisionTask $task, Service $service): array
    {
        if ($task->operation === 'sync_nat') {
            $service->loadMissing('natMappings');

            return [
                'external_ip' => $service->nat_ip,
                'mappings' => $service->natMappings
                    ->whereIn('status', ['pending', 'active'])
                    ->flatMap(function ($mapping) {
                        $protocols = $mapping->protocol === 'tcp+udp' ? ['tcp', 'udp'] : [$mapping->protocol];

                        return collect($protocols)->map(fn ($protocol) => [
                            'external_ip' => $mapping->external_ip,
                            'external_port' => $mapping->external_port,
                            'internal_ip' => $mapping->internal_ip,
                            'internal_port' => $mapping->internal_port,
                            'protocol' => $protocol,
                            'status' => 'active',
                        ]);
                    })->values()->all(),
            ];
        }

        if ($task->operation !== 'create') {
            return ['action' => $this->actionFor($task->operation)];
        }

        $service->loadMissing('product');
        $specs = $service->specs ?? [];
        $metadata = $service->product?->metadata ?? [];
        $controller = is_array($metadata['controller'] ?? null) ? $metadata['controller'] : [];

        $payload = [
            'name' => Str::limit(preg_replace('/[^A-Za-z0-9-]/', '-', $service->service_no) ?: 'deviy-vm', 40, ''),
            'cores' => (int) ($specs['cpu'] ?? 2),
            'memory_mb' => (int) ($specs['memory_gb'] ?? 4) * 1024,
            'disk_gb' => (int) ($specs['disk_gb'] ?? 40),
            'data_disk_gb' => (int) ($specs['data_disk_gb'] ?? 0),
            'upload_mbps' => (int) ($specs['upload_mbps'] ?? $specs['bandwidth_mbps'] ?? 10),
            'download_mbps' => (int) ($specs['download_mbps'] ?? $specs['bandwidth_mbps'] ?? 10),
            'ostype' => (string) ($specs['ostype'] ?? $controller['ostype'] ?? 'l26'),
            'network_mode' => (string) ($controller['network_mode'] ?? 'private'),
            'password' => (string) ($specs['remote']['password'] ?? ''),
            'start' => true,
        ];

        $imagePath = $this->imagePath($controller, $service);
        if ($imagePath !== '') {
            $payload['image'] = $imagePath;
        }

        if (! empty($controller['ip_pool_id'])) {
            $payload['ip_pool_id'] = $controller['ip_pool_id'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $controller
     */
    private function imagePath(array $controller, Service $service): string
    {
        $imageId = trim((string) ($controller['image_id'] ?? ''));
        $desiredOs = strtolower(trim((string) ($service->specs['ostype'] ?? '')));
        $configuredPath = trim((string) ($controller['image_path'] ?? ''));

        $images = [];
        try {
            $images = app(ControllerGateway::class)->images($service->node?->agent_node_id);
        } catch (Throwable $exception) {
            report($exception);
        }

        $configured = null;
        foreach ($images as $image) {
            if ((string) ($image['id'] ?? '') === $imageId) {
                $configured = $image;
                break;
            }
        }

        if ($configured !== null && $this->imageMatchesOs($configured, $desiredOs)) {
            return (string) ($configured['path'] ?? $configuredPath);
        }

        if ($desiredOs !== '') {
            $candidates = array_values(array_filter($images, fn (array $image): bool => $this->imageMatchesOs($image, $desiredOs)));
            if (str_starts_with($desiredOs, 'win') && isset($candidates[0]['path'])) {
                return (string) $candidates[0]['path'];
            }

            $osName = strtolower(strtok((string) $service->os, ' '));
            if ($osName !== '') {
                $named = array_values(array_filter($candidates, fn (array $image): bool => str_contains(strtolower((string) ($image['name'] ?? '')), $osName)));
                if ($named !== []) {
                    return (string) ($named[0]['path'] ?? '');
                }
            }

            if (str_contains($osName, 'linux') && isset($candidates[0]['path'])) {
                return (string) $candidates[0]['path'];
            }

            return '';
        }

        return $configuredPath;
    }

    private function imageMatchesOs(array $image, string $desiredOs): bool
    {
        if ($desiredOs === '') {
            return true;
        }

        $imageOs = strtolower((string) ($image['ostype'] ?? ''));
        $desiredWindows = str_starts_with($desiredOs, 'win');
        $imageWindows = str_contains($imageOs, 'win');

        return $desiredWindows === $imageWindows;
    }

    private function pathFor(ProvisionTask $task, Service $service): string
    {
        if ($task->operation === 'create') {
            return '/vms';
        }

        if ($task->operation === 'sync_nat') {
            return '/nat/sync';
        }

        $vmid = $this->vmid($service);

        return '/vms/'.$vmid.'/action';
    }

    private function actionFor(string $operation): string
    {
        return match ($operation) {
            'start', 'unsuspend' => 'start',
            'stop', 'suspend' => 'stop',
            'reboot' => 'reboot',
            'terminate' => 'delete',
            default => throw new \RuntimeException('暂不支持的资源操作：'.$operation),
        };
    }

    private function vmid(Service $service): string
    {
        $ref = (string) $service->external_ref;

        if (str_starts_with($ref, 'vmid:')) {
            return substr($ref, 5);
        }

        if (str_starts_with($ref, 'id:')) {
            return substr($ref, 3);
        }

        throw new \RuntimeException('服务尚未绑定 VMID，无法执行该操作。');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sendProvisionedEmail(Service $service): void
    {
        $service->loadMissing('user');
        if (! $service->user) {
            return;
        }

        MailSettings::sendTemplate('service_provisioned', $service->user, [
            'service_name' => (string) $service->name,
            'service_no' => (string) $service->service_no,
            'date' => $service->expires_at?->format('Y-m-d H:i'),
            'console_url' => route('console.services.show', $service),
        ]);
    }

    private function applyResult(ProvisionTask $task, Service $service, array $body): void
    {
        if ($task->operation === 'sync_nat') {
            $task->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'last_error' => null,
                'result' => $body,
            ]);

            if ($service->status === 'terminated') {
                $service->update([
                    'nat_ip' => null,
                    'nat_port_start' => null,
                    'nat_port_end' => null,
                ]);
            }

            return;
        }

        if ($task->operation === 'create') {
            $resourceId = (string) ($body['id'] ?? $body['vmid'] ?? '');

            if ($resourceId === '') {
                throw new \RuntimeException('Agent 未返回 VM ID。');
            }

            $service->update([
                'status' => 'active',
                'provision_status' => 'succeeded',
                'external_ref' => (ctype_digit($resourceId) ? 'vmid:' : 'id:').$resourceId,
                'ip_address' => $body['ip'] ?? $service->ip_address,
            ]);

            $this->applyBandwidth($service, $resourceId);

            $task->update(['status'=>'succeeded','completed_at'=>now(),'last_error'=>null,'result'=>$body]);
            try {
                app(ServiceNetworkSync::class)->sync($service,true);
            } catch (Throwable $exception) {
                $specs=(array)$service->fresh()->specs;$specs['network_sync_error']=$exception->getMessage();
                $service->update(['specs'=>$specs,'provision_status'=>'network_pending']);
                report($exception);
                return;
            }
            $this->sendProvisionedEmail($service);
        } else {
            $service->update([
                'status' => match ($task->operation) {
                    'suspend', 'stop' => 'suspended',
                    'terminate' => 'terminated',
                    'unsuspend', 'start' => 'active',
                    default => $service->status,
                },
                'provision_status' => 'succeeded',
            ]);
        }

        $task->update([
            'status' => 'succeeded',
            'completed_at' => now(),
            'last_error' => null,
            'result' => $body,
        ]);
    }

    private function applyBandwidth(Service $service, string $resourceId): void
    {
        $upload = (int) ($service->specs['upload_mbps'] ?? 0);
        $download = (int) ($service->specs['download_mbps'] ?? 0);
        if ($upload <= 0 && $download <= 0) {
            return;
        }

        $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
        if ($nodeId === '' || $resourceId === '') {
            return;
        }

        try {
            $this->client()
                ->post("/api/nodes/{$nodeId}/vms/{$resourceId}/bandwidth", [
                    'upload_mbps' => $upload,
                    'download_mbps' => $download,
                ])
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('Service bandwidth apply failed', [
                'service_id' => $service->id,
                'resource_id' => $resourceId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function markFailed(ProvisionTask $task, Throwable $exception): void
    {
        $attempts = $task->attempts;
        $failed = $attempts >= 3;

        $task->update([
            'status' => $failed ? 'failed' : 'queued',
            'last_error' => Str::limit($exception->getMessage(), 1000),
            'completed_at' => $failed ? now() : null,
        ]);

        if ($failed && $task->service && $task->operation === 'create') {
            $task->service->update(['provision_status' => 'failed']);
        }

        Log::error('Provision task failed', [
            'task_id' => $task->id,
            'operation' => $task->operation,
            'exception' => $exception,
        ]);
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) config('deviy.controller_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('deviy.timeout', 30));

        $token = (string) config('deviy.controller_token');
        if ($token !== '') {
            $client = $client->withToken($token);
        }

        $ca = (string) config('deviy.controller_ca');
        $cert = (string) config('deviy.controller_cert');
        $key = (string) config('deviy.controller_key');
        $options = [];
        if ($ca !== '') {
            $options['verify'] = $ca;
        }
        if ($cert !== '' && $key !== '') {
            $options['cert'] = $cert;
            $options['ssl_key'] = $key;
        }
        if ($options !== []) {
            $client = $client->withOptions($options);
        }

        return $client;
    }
}
