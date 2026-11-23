<?php

namespace App\Services\Commerce;

use App\Models\Admin;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Services\Mofang\Contracts\MofangGateway;
use App\Services\Provisioning\ControllerGateway;
use App\Services\Pterodactyl\PterodactylSettings;
use App\Services\Qzvps\QzvpsClient;
use App\Support\Colocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CustomerServiceDeletion
{
    public function __construct(private readonly IpAllocator $ipAllocator, private readonly AuditLogger $audit) {}

    /** @return array{completed: bool, message: string} */
    public function delete(Service $service, Admin $admin, string $reason): array
    {
        return Cache::lock('customer-service-delete:'.$service->id, 180)->block(3, function () use ($service, $admin, $reason): array {
            $service = Service::with(['product', 'node', 'natMappings'])->findOrFail($service->id);
            $source = $this->source($service);
            if ($source !== 'manual' && Service::where('id', '!=', $service->id)->where('external_ref', $service->external_ref)
                ->when($source === 'controller', fn ($query) => $query->where('node_id', $service->node_id))->exists()) {
                throw new RuntimeException('该资源还绑定了其他本站服务，已阻止销毁，请先核对重复绑定。');
            }
            $state = (array) data_get($service->specs, 'admin_deletion', []);
            if ($service->provisionTasks()->whereIn('status', ['queued', 'dispatched', 'running'])->exists()) {
                throw new RuntimeException('该服务还有正在处理的运维任务，请等待完成后再删除。');
            }
            if (\App\Models\UpstreamOrder::where('service_id', $service->id)->whereNotIn('status', ['active', 'suspended', 'terminated', 'cancelled', 'failed'])->exists()) {
                throw new RuntimeException('该服务还有未完成的供应商订单，请先核对订单结果。');
            }
            if (\App\Models\OrderItem::where('service_id', $service->id)->whereHas('order', fn ($query) => $query->where('status', 'pending'))->exists()) {
                throw new RuntimeException('该服务还有待支付订单，请先处理或取消订单，避免删除后继续付款。');
            }
            if (data_get($service->specs, 'legacy_import.operation_hold', false) && $source !== 'manual') {
                throw new RuntimeException('该实例绑定正在核查，请先确认真实资源后再执行同步销毁。');
            }

            $task = ! empty($state['task_id']) ? ProvisionTask::where('service_id', $service->id)->find($state['task_id']) : null;
            try {
                if ($this->confirmedGone($service, $source)) {
                    return $this->finish($service, $admin, $reason, $source, $task);
                }
                if ($state !== []) {
                    return ['completed' => false, 'message' => '已核对：资源尚未确认销毁，本站服务继续保留。不会重复下发销毁命令，请在对应平台核对处理进度后再次核对。'];
                }
                $task = ProvisionTask::create([
                    'service_id' => $service->id, 'operation' => 'terminate', 'status' => 'needs_review',
                    'attempts' => 1, 'dispatched_at' => now(),
                    'payload' => ['source' => 'admin_customer_verified_delete', 'admin_id' => $admin->id, 'reason' => $reason, 'provider' => $source, 'external_ref' => $service->external_ref],
                ]);
                $specs = (array) $service->specs;
                $specs['admin_deletion'] = ['state' => 'requested', 'task_id' => $task->id, 'requested_at' => now()->toIso8601String(), 'provider' => $source];
                $service->update(['specs' => $specs, 'auto_renew' => false, 'provision_status' => 'deletion_pending']);
                $this->audit->record($admin, 'customer.service_delete_requested', '申请同步销毁服务：'.$service->service_no, $service, ['provider' => $source, 'reason' => $reason, 'external_ref' => $service->external_ref]);
                $this->requestDeletion($service, $source);
                $task->update(['result' => ['accepted' => true, 'provider' => $source]]);
                if ($this->confirmedGone($service, $source)) {
                    return $this->finish($service, $admin, $reason, $source, $task);
                }
                $task->update(['last_error' => '接口已接受请求，等待资源销毁结果确认。']);

                return ['completed' => false, 'message' => '销毁请求已提交，资源尚未确认删除，本站服务继续保留。稍后点击“核对删除结果”继续核对，确认成功后才会移除。'];
            } catch (Throwable $exception) {
                if ($task) {
                    $task->update(['status' => 'needs_review', 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
                }
                $this->audit->record($admin, 'customer.service_delete_unconfirmed', '服务销毁未确认：'.$service->service_no, $service, ['provider' => $source, 'reason' => $reason]);
                report($exception);

                return ['completed' => false, 'message' => '同步销毁未确认，本站服务和资源绑定已保留。请检查对应平台或接口，再核对删除结果。'];
            }
        });
    }

    private function source(Service $service): string
    {
        $source = (string) $service->product?->upstream_source;
        $ref = trim((string) $service->external_ref);
        if (Colocation::service($service) || ($source === 'manual' && $ref === '')) {
            return 'manual';
        }
        foreach (['mofang', 'qzvps', 'pterodactyl'] as $provider) {
            if ($source === $provider || str_starts_with($ref, $provider.':')) {
                if ($source !== $provider || ! preg_match('/^'.$provider.':[1-9]\d*$/D', $ref)) {
                    throw new RuntimeException('商品类型和资源绑定不匹配，已阻止删除。');
                }

                return $provider;
            }
        }
        if (in_array($source, ['', 'local'], true) && $service->node?->agent_node_id && preg_match('/^(vmid|id):[^\s\/]+$/D', $ref)) {
            return 'controller';
        }
        throw new RuntimeException('资源绑定不完整，不能只删除本站记录。请先核对对应平台的真实实例。');
    }

    private function confirmedGone(Service $service, string $source): bool
    {
        $id = explode(':', (string) $service->external_ref, 2)[1] ?? '';
        if ($source === 'manual') {
            return true;
        }
        if ($source === 'controller') {
            return ! app(ControllerGateway::class)->virtualMachineExists((string) $service->node->agent_node_id, $id);
        }
        if ($source === 'qzvps') {
            return ! app(QzvpsClient::class)->hostExists((int) $id);
        }
        if ($source === 'pterodactyl') {
            return ! PterodactylSettings::client()->serverExists($id);
        }
        $detail = app(MofangGateway::class)->fetchService($id);
        $host = (array) (data_get($detail, 'data.host_data') ?? data_get($detail, 'host_data') ?? []);
        if ((string) ($host['id'] ?? '') !== $id) {
            throw new RuntimeException('供应商返回的资源编号与绑定不符，无法确认销毁结果。');
        }
        $status = strtolower((string) ($host['domainstatus'] ?? $host['status'] ?? ''));

        return in_array($status, ['terminated', 'deleted'], true);
    }

    private function requestDeletion(Service $service, string $source): void
    {
        $id = explode(':', (string) $service->external_ref, 2)[1] ?? '';
        match ($source) {
            'mofang' => app(MofangGateway::class)->performServiceAction($id, 'terminate'),
            'qzvps' => app(QzvpsClient::class)->action((int) $id, 'delete'),
            'pterodactyl' => PterodactylSettings::client()->deleteServer($id),
            'controller' => app(ControllerGateway::class)->deleteVirtualMachine((string) $service->node->agent_node_id, $id),
            default => throw new RuntimeException('不支持的同步销毁类型。'),
        };
    }

    /** @return array{completed: bool, message: string} */
    private function finish(Service $service, Admin $admin, string $reason, string $source, ?ProvisionTask $task): array
    {
        if ($source === 'controller') {
            $gateway = app(ControllerGateway::class);
            $nodeId = (string) $service->node->agent_node_id;
            $rules = $gateway->natRules($nodeId);
            $changed = false;
            foreach ($service->natMappings->whereIn('status', ['pending', 'active']) as $mapping) {
                foreach ($rules as $rule) {
                    if ((string) ($rule['internal_ip'] ?? '') === (string) $mapping->internal_ip
                        && (int) ($rule['internal_port'] ?? 0) === $mapping->internal_port
                        && (string) ($rule['external_ip'] ?? '') === (string) $mapping->external_ip
                        && (int) ($rule['external_port'] ?? 0) === $mapping->external_port
                        && in_array((string) ($rule['protocol'] ?? ''), $mapping->protocol === 'tcp+udp' ? ['tcp', 'udp'] : [$mapping->protocol], true)) {
                        $gateway->deleteNatRule($nodeId, (string) $rule['id']);
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                $gateway->applyNetwork($nodeId, 'nat');
            }
        }
        DB::transaction(function () use ($service, $admin, $reason, $source, $task): void {
            $current = Service::lockForUpdate()->findOrFail($service->id);
            $this->ipAllocator->release($current);
            $current->natMappings()->whereIn('status', ['pending', 'active'])->update(['status' => 'released']);
            $current->update(['status' => 'terminated', 'provision_status' => 'terminated', 'auto_renew' => false]);
            if ($task) {
                $task->update(['status' => 'succeeded', 'completed_at' => now(), 'last_error' => null, 'result' => ['confirmed' => true, 'provider' => $source, 'external_ref' => $current->external_ref]]);
            }
            $this->audit->record($admin, 'customer.service_deleted', '删除客户服务：'.$current->service_no, $current, ['provider' => $source, 'reason' => $reason, 'external_ref' => $current->external_ref, 'remote_confirmed' => $source !== 'manual']);
            $current->delete();
        });

        return ['completed' => true, 'message' => $source === 'manual' ? '服务登记已移除，历史订单和账务记录已保留。' : '对应平台已确认资源终止，本站服务已移除，历史订单和账务记录已保留。'];
    }
}
