<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Models\ServiceMetricSample;
use App\Services\AuditLogger;
use App\Services\Billing\RenewalService;
use App\Services\Commerce\ResourceCleanupService;
use App\Services\Mofang\Contracts\MofangGateway;
use App\Services\Provisioning\ControllerGateway;
use App\Services\Pterodactyl\PterodactylSettings;
use App\Services\Qzvps\QzvpsClient;
use App\Services\Qzvps\QzvpsServiceSync;
use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $provider = $request->string('provider')->toString();
        $source = $request->string('source')->toString();

        $services = Service::query()
            ->with(['user', 'product', 'node'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('service_no', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%")
                ->orWhere('nat_ip', 'like', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($provider !== '', fn ($query) => $query->whereHas('node', fn ($node) => $node->where('provider', $provider)))
            ->when($source === 'mofang', fn ($query) => $query->whereHas('product', fn ($product) => $product->where('upstream_source', 'mofang')))
            ->when($source === 'pterodactyl', fn ($query) => $query->whereHas('product', fn ($product) => $product->where('upstream_source', 'pterodactyl')))
            ->when($source === 'local', fn ($query) => $query->whereHas('product', fn ($product) => $product->whereNotIn('upstream_source', ['mofang', 'pterodactyl'])->orWhereNull('upstream_source')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.services.index', compact('services', 'search', 'status', 'provider', 'source'));
    }

    public function show(Service $service, RenewalService $renewals): View
    {
        if (\App\Support\Colocation::service($service)) {
            return view('console.services.colocation', ['service'=>$service->load('product','user'),'adminView'=>true]);
        }
        $service->load([
            'user',
            'product',
            'node',
            'upstreamOrder',
            'natMappings' => fn ($query) => $query->latest(),
            'provisionTasks' => fn ($query) => $query->latest()->limit(20),
        ]);

        if ($service->product?->upstream_source === 'qzvps' && in_array($service->status, ['active', 'suspended', 'stopped', 'provisioning'], true) && ! data_get($service->specs, 'legacy_import.operation_hold', false)) {
            try {
                app(QzvpsServiceSync::class)->sync($service);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($service->product?->upstream_source === 'mofang') {
            $detail = [];
            $syncError = null;
            try {
                $detail = Cache::remember('mofang:service:'.substr((string) $service->external_ref, 7), 60, fn () => app(MofangGateway::class)->fetchService(substr((string) $service->external_ref, 7)));
                app(\App\Services\Mofang\MofangServiceSync::class)->sync($service, $detail);
            } catch (\Throwable $exception) {
                report($exception);
                $syncError = '上游详情暂时不可用，当前显示上次同步记录。';
            }
            $type = \App\Support\MofangServiceType::forService($service, $detail);
            if (in_array($type, ['cdn', 'bare_metal'], true)) {
                return view($type === 'bare_metal' ? 'console.services.bare-metal' : 'console.services.upstream-special', app(\App\Http\Controllers\Console\ServiceController::class)->specialData($service, $detail, $type, $renewals, true, $syncError));
            }
        }

        if ($service->product?->upstream_source === 'local' && $service->product?->type === 'nat_vm') {
            try { app(\App\Services\Provisioning\ServiceNetworkSync::class)->sync($service); } catch (\Throwable $exception) { report($exception); }
        }
        $upstreamDetail = [];
        $upstreamTraffic = null;
        $reinstallOs = [];
        if ($service->product?->upstream_source === 'mofang' && str_starts_with((string) $service->external_ref, 'mofang:')) {
            try {
                $ref = substr((string) $service->external_ref, 7);
                $gateway = app(MofangGateway::class);
                $upstreamDetail = Cache::remember('mofang:service:'.$ref, 60, fn () => $gateway->fetchService($ref));
                $reinstallOs = (array) data_get($upstreamDetail, 'data.cloud_os', []);
                $upstreamStatus = strtolower((string) data_get($upstreamDetail, 'data.host_data.domainstatus', ''));
                $mappedStatus = match ($upstreamStatus) {
                    'active' => 'active',
                    'suspended' => 'suspended',
                    'terminated', 'cancelled' => 'terminated',
                    'pending' => 'pending',
                    default => null,
                };
                if ($mappedStatus !== null && $service->status !== $mappedStatus) {
                    $service->update(['status' => $mappedStatus]);
                    $service->refresh();
                }
                $traffic = $gateway->fetchTraffic($ref);
                $upstreamTraffic = $this->upstreamTraffic($traffic);
                if($upstreamTraffic){$upstreamTraffic['packets']=app(\App\Services\Mofang\MofangTrafficOrders::class)->packets($service,$traffic);}
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $localSample = null;
        if ($service->product?->upstream_source !== 'mofang') {
            $localSample = ServiceMetricSample::query()->where('service_id', $service->id)->latest('recorded_at')->first();
            $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
            if ($nodeId !== '') {
                try {
                    $images = app(ControllerGateway::class)->images($nodeId);
                    $reinstallOs = array_values(array_map(fn (array $image): array => [
                        'id' => (string) ($image['path'] ?? $image['name'] ?? ''),
                        'name' => (string) ($image['name'] ?? $image['path'] ?? '系统'),
                        'group' => (string) ($image['ostype'] ?? '系统镜像'),
                    ], array_filter($images, fn (array $image): bool => (string) ($image['path'] ?? '') !== '')));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        if ($service->product?->upstream_source === 'qzvps') {
            $reinstallOs = collect(app(\App\Services\Qzvps\QzvpsCatalog::class)->imagesForNode((string) data_get($service->specs, 'qzvps.node_id')))
                ->map(fn (array $image): array => ['id' => (string) ($image['name'] ?? ''), 'name' => (string) ($image['name'] ?? ''), 'group' => (string) ($image['type'] ?? '其他')])->all();
        }

        $renewalPrices = $renewals->pricesFor($service);
        $renewalQuotes = $renewalPrices->mapWithKeys(fn ($price) => [
            $price->billing_cycle => $renewals->quote($service, $price->billing_cycle)['amount'],
        ]);

        return view('admin.services.show', compact(
            'service',
            'upstreamDetail',
            'upstreamTraffic',
            'reinstallOs',
            'localSample',
            'renewalPrices',
            'renewalQuotes',
        ) + [
            'cycles' => Billing::cycles(),
            'pterodactylBaseUrl' => $service->product?->upstream_source === 'pterodactyl'
                ? (string) data_get(PterodactylSettings::configuration(), 'base_url', 'https://pl.deviy.cn')
                : '',
        ]);
    }

    public function vnc(Service $service, ControllerGateway $gateway): View|RedirectResponse
    {
        abort_if(\App\Support\Colocation::service($service), 422, '托管设备不提供自动化运维操作，请维护托管登记资料。');
        if ($service->product?->upstream_source === 'mofang') {
            $ref = (string) $service->external_ref;
            if (! str_starts_with($ref, 'mofang:')) {
                return back()->withErrors(['vnc' => '实例尚未绑定。']);
            }
            try {
                $ticket = app(MofangGateway::class)->fetchVnc(substr($ref, 7));
            } catch (\Throwable $exception) {
                return back()->withErrors(['vnc' => 'VNC 连接失败：'.$exception->getMessage()]);
            }
            $url = (string) (data_get($ticket, 'data.url') ?? '');
            if ($url === '') {
                return back()->withErrors(['vnc' => '没有返回 VNC 地址。']);
            }

            return view('console.services.vnc', compact('service') + ['vncUrl' => $url]);
        }

        if ($service->product?->upstream_source === 'qzvps') {
            $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
            if ($hostId <= 0) {
                return back()->withErrors(['vnc' => '实例尚未绑定轻舟主机。']);
            }

            return view('console.services.vnc', compact('service') + [
                'vncUrl' => app(QzvpsClient::class)->consoleUrl($hostId),
            ]);
        }

        $ref = trim((string) $service->external_ref);
        $vmid = str_starts_with($ref, 'vmid:') ? substr($ref, 5) : (str_starts_with($ref, 'id:') ? substr($ref, 3) : $ref);
        $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
        if ($vmid === '' || $nodeId === '') {
            return back()->withErrors(['vnc' => '实例尚未绑定主控节点。']);
        }
        try {
            $ticket = $gateway->vncTicket($nodeId, $vmid);
        } catch (\Throwable $exception) {
            return back()->withErrors(['vnc' => 'VNC 连接失败：'.$exception->getMessage()]);
        }
        $path = (string) ($ticket['url'] ?? '');
        if ($path === '') {
            return back()->withErrors(['vnc' => '没有返回 VNC 地址。']);
        }

        return view('console.services.vnc', compact('service') + ['vncUrl' => $gateway->publicUrl().$path]);
    }


    public function traffic(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        abort_unless($service->product?->upstream_source==='mofang'&&str_starts_with((string)$service->external_ref,'mofang:'),422);
        $data=$request->validate(['flow_packet_id'=>['required','integer','min:1']]);
        $detail=app(MofangGateway::class)->fetchTraffic(substr($service->external_ref,7));$orders=app(\App\Services\Mofang\MofangTrafficOrders::class);
        $packet=collect($orders->packets($service,$detail))->firstWhere('id',(int)$data['flow_packet_id']);abort_unless(is_array($packet),422,'所选流量包不属于该实例。');
        $order=$orders->create($service,$packet);$audit->record($request->user('admin'),'service.traffic_invoice','创建客户流量包待付账单',$service,['order_id'=>$order->id]);
        return redirect()->route('admin.orders.show',$order)->with('status','客户流量包账单已创建，付款后执行交付。');
    }

    public function resetPassword(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        abort_if(\App\Support\Colocation::service($service), 422, '托管设备不提供自动化运维操作，请维护托管登记资料。');
        if ($service->product?->upstream_source === 'mofang' && \App\Support\MofangServiceType::forService($service) === 'cdn') {
            return back()->withErrors(['service' => 'CDN 服务请通过 CDN 面板管理，不支持服务器操作。']);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'force_shutdown' => ['nullable', 'boolean'],
        ]);

        try {
            if ($service->product?->upstream_source === 'qzvps') {
                if (data_get($service->specs, 'legacy_import.operation_hold', false)) {
                    return back()->withErrors(['password' => '该实例正在核查，暂不可修改密码。']);
                }
                app(QzvpsClient::class)->resetPassword((int) preg_replace('/^qzvps:/', '', (string) $service->external_ref), $data['password']);
            } elseif ($service->product?->upstream_source === 'mofang') {
                if (! $request->boolean('force_shutdown')) {
                    return back()->withErrors(['password' => '请先同意强制关机。']);
                }
                app(MofangGateway::class)->performServiceAction(
                    substr((string) $service->external_ref, 7),
                    'crack_pass',
                    ['password' => $data['password'], 'force_shutdown' => true],
                );
            } else {
                $remote = (array) data_get($service->specs, 'remote', []);
                $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
                $ref = trim((string) $service->external_ref);
                $vmid = str_starts_with($ref, 'vmid:') ? substr($ref, 5) : (str_starts_with($ref, 'id:') ? substr($ref, 3) : $ref);
                $internalIp = (string) (data_get($service->specs, 'internal_ip') ?: $service->natMappings->first()?->internal_ip);
                $username = (string) ($remote['username'] ?? (str_contains(strtolower((string) $service->os), 'windows') ? 'Administrator' : 'root'));
                if ($nodeId === '' || $vmid === '' || $internalIp === '') {
                    return back()->withErrors(['password' => '实例信息不完整，无法重置密码。']);
                }
                app(ControllerGateway::class)->changeGuestPassword($nodeId, $vmid, $internalIp, $username, $data['password']);
            }
        } catch (\Throwable $exception) {
            return back()->withErrors(['password' => '重置密码失败：'.$exception->getMessage()]);
        }

        $specs = (array) $service->specs;
        $specs['remote'] = array_merge((array) ($specs['remote'] ?? []), ['password' => $data['password']]);
        $service->update(['specs' => $specs]);
        $audit->record($request->user('admin'), 'service.password_reset', '重置实例密码：'.$service->service_no, $service);

        return back()->with('status', '实例密码已重置。');
    }

    public function extend(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        $data = $request->validate(['expires_at' => ['required', 'date']]);
        $expires = Carbon::parse($data['expires_at']);
        $service->update([
            'expires_at' => $expires,
            'next_due_at' => $expires->copy()->subDays(7),
        ]);
        $audit->record($request->user('admin'), 'service.extended', '调整到期时间：'.$service->service_no, $service);

        return back()->with('status', '到期时间已更新。');
    }

    public function toggleAutoRenew(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        abort_if(\App\Support\Colocation::service($service), 422, '托管设备不提供自动化运维操作，请维护托管登记资料。');
        $service->update(['auto_renew' => ! $service->auto_renew]);
        $audit->record($request->user('admin'), 'service.auto_renew', '切换自动续费：'.$service->service_no, $service);

        return back()->with('status', '自动续费状态已更新。');
    }

    public function updateRemote(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        abort_if(\App\Support\Colocation::service($service), 422, '托管设备不提供自动化运维操作，请维护托管登记资料。');
        $data = $request->validate([
            'remote_address' => ['nullable', 'string', 'max:255'],
            'remote_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'remote_username' => ['nullable', 'string', 'max:80'],
            'remote_password' => ['nullable', 'string', 'max:255'],
            'remote_protocol' => ['nullable', 'in:rdp,ssh,vnc,other'],
            'remote_note' => ['nullable', 'string', 'max:500'],
        ]);

        $specs = is_array($service->specs) ? $service->specs : [];
        $specs['remote'] = [
            'address' => trim((string) ($data['remote_address'] ?? '')),
            'port' => isset($data['remote_port']) && $data['remote_port'] !== '' ? (int) $data['remote_port'] : null,
            'username' => trim((string) ($data['remote_username'] ?? '')),
            'password' => (string) ($data['remote_password'] ?? ''),
            'protocol' => (string) ($data['remote_protocol'] ?? ''),
            'note' => trim((string) ($data['remote_note'] ?? '')),
        ];

        $service->update(['specs' => $specs]);
        $audit->record($request->user('admin'), 'service.remote_updated', '更新远程连接信息：'.$service->service_no, $service);

        return back()->with('status', '远程连接信息已更新。');
    }

    /** @return array{used: ?float, total: ?float, packets: array<int, array<string, mixed>>} */
    private function upstreamTraffic(array $payload): array
    {
        $data = is_array(data_get($payload, 'data')) ? data_get($payload, 'data') : $payload;
        if (is_array(data_get($data, 'host_data'))) {
            $data = array_replace($data, data_get($data, 'host_data'));
        }
        if (is_array(data_get($data, 'dcim'))) {
            $data['flowpacket'] ??= data_get($data, 'dcim.flowpacket');
            $data['flow_packet_use_list'] ??= data_get($data, 'dcim.flow_packet_use_list');
        }

        $total = data_get($data, 'flow_limit') ?? data_get($data, 'bwlimit') ?? data_get($data, 'traffic_limit') ?? data_get($data, 'total_flow');
        $used = data_get($data, 'flow_used') ?? data_get($data, 'traffic_used') ?? data_get($data, 'used_flow') ?? data_get($data, 'flow_packet_use') ?? data_get($data, 'bwusage');
        $packets = data_get($data, 'flow_packet_use_list');
        if (! is_array($packets) || $packets === []) {
            $packets = data_get($data, 'flowpacket');
        }

        return [
            'used' => is_numeric($used) ? (float) $used : null,
            'total' => is_numeric($total) ? (float) $total : null,
            'packets' => is_array($packets) ? array_values(array_filter($packets, 'is_array')) : [],
        ];
    }

    public function action(Request $request, Service $service, AuditLogger $audit, ResourceCleanupService $cleanup): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        abort_if(\App\Support\Colocation::service($service), 422, '托管设备不提供自动化运维操作，请维护托管登记资料。');
        if ($service->product?->upstream_source === 'mofang' && \App\Support\MofangServiceType::forService($service) === 'cdn') {
            return back()->withErrors(['service' => 'CDN 服务请通过 CDN 面板管理，不支持服务器操作。']);
        }

        $data = $request->validate([
            'action' => ['required', 'in:start,stop,reboot,reinstall,suspend,unsuspend,terminate'],
            'os' => ['nullable', 'string', 'max:500'],
        ]);

        if ($service->product?->upstream_source === 'pterodactyl') {
            ProvisionTask::create([
                'service_id' => $service->id,
                'operation' => $data['action'],
                'status' => 'queued',
                'payload' => ['source' => 'admin', 'admin_id' => $request->user('admin')->id],
            ]);
            $service->update(['provision_status' => 'queued']);
            $audit->record($request->user('admin'), 'service.'.$data['action'], '翼龙实例操作：'.$service->service_no, $service);

            return back()->with('status', '翼龙面板操作已提交。');
        }

        if ($service->product?->upstream_source === 'mofang') {
            if (! str_starts_with((string) $service->external_ref, 'mofang:')) {
                return back()->withErrors(['action' => '实例尚未绑定。']);
            }
            if ($data['action'] === 'reinstall' && empty($data['os'])) {
                return back()->withErrors(['action' => '请选择要安装的操作系统。']);
            }
            try {
                app(MofangGateway::class)->performServiceAction(
                    substr((string) $service->external_ref, 7),
                    $data['action'],
                    ['os' => isset($data['os']) ? (int) $data['os'] : null],
                );
            } catch (\Throwable $exception) {
                return back()->withErrors(['action' => '操作失败：'.$exception->getMessage()]);
            }

            $service->update([
                'status' => match ($data['action']) {
                    'stop', 'suspend' => 'suspended',
                    'reinstall' => 'provisioning',
                    'terminate' => 'terminated',
                    default => 'active',
                },
                'provision_status' => $data['action'] === 'reinstall' ? 'upstream_provisioning' : 'success',
            ]);
            if ($data['action'] === 'terminate') {
                $cleanup->releaseForTermination($service);
            }
            $audit->record($request->user('admin'), 'service.'.$data['action'], '实例操作：'.$service->service_no, $service);

            return back()->with('status', '实例操作已提交。');
        }

        if ($data['action'] === 'reinstall') {
            $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
            $ref = trim((string) $service->external_ref);
            $vmid = str_starts_with($ref, 'vmid:') ? substr($ref, 5) : (str_starts_with($ref, 'id:') ? substr($ref, 3) : $ref);
            $image = (string) ($data['os'] ?? '');
            if ($nodeId === '' || $vmid === '' || $image === '') {
                return back()->withErrors(['action' => '实例或镜像信息不完整。']);
            }
            try {
                $allowed = collect(app(ControllerGateway::class)->images($nodeId))->pluck('path')->all();
                if (! in_array($image, $allowed, true)) {
                    return back()->withErrors(['action' => '请选择该节点可用的系统镜像。']);
                }
                $imageInfo = collect(app(ControllerGateway::class)->images($nodeId))->firstWhere('path', $image);
                app(ControllerGateway::class)->reinstallVirtualMachine($nodeId, $vmid, [
                    'image' => $image,
                    'ostype' => (string) ($imageInfo['ostype'] ?? ''),
                ]);
            } catch (\Throwable $exception) {
                return back()->withErrors(['action' => '重装失败：'.$exception->getMessage()]);
            }

            $service->update(['status' => 'provisioning', 'provision_status' => 'queued']);
            $audit->record($request->user('admin'), 'service.reinstall', '重装实例：'.$service->service_no, $service);

            return back()->with('status', '重装任务已提交。');
        }

        $status = match ($data['action']) {
            'start', 'unsuspend' => 'active',
            'stop', 'suspend' => 'suspended',
            'terminate' => 'terminated',
            'reboot' => $service->status,
        };

        $service->update(['status' => $status, 'provision_status' => 'queued']);

        if ($data['action'] === 'terminate') {
            $cleanup->releaseForTermination($service);
        }

        ProvisionTask::create([
            'service_id' => $service->id,
            'operation' => $data['action'],
            'status' => 'queued',
            'payload' => ['source' => 'admin', 'admin_id' => $request->user('admin')->id],
        ]);

        $audit->record($request->user('admin'), 'service.'.$data['action'], '服务操作：'.$service->service_no, $service);

        return back()->with('status', '操作已下发给主控。');
    }
}
