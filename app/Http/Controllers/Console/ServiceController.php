<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\NatMapping;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Models\ServiceMetricSample;
use App\Services\Billing\RenewalService;
use App\Services\Commerce\ResourceCleanupService;
use App\Services\Mofang\Contracts\MofangGateway;
use App\Services\Provisioning\ControllerGateway;
use App\Services\Provisioning\MetricCollector;
use App\Services\Provisioning\ServiceNetworkSync;
use App\Services\Pterodactyl\PterodactylSettings;
use App\Services\Qzvps\QzvpsCatalog;
use App\Services\Qzvps\QzvpsClient;
use App\Services\Qzvps\QzvpsServiceSync;
use App\Support\Billing;
use App\Services\Mofang\MofangCartClient;
use App\Services\Mofang\MofangServiceSync;
use App\Support\MofangServiceType;
use App\Support\RemoteEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $resourceSearch = $request->string('q')->trim()->toString();
        $allServices = $request->user()->services()
            ->with(['product.group.parent.parent', 'node', 'natMappings'])
            ->latest()
            ->get();
        $groupSummaries = [];
        $serviceGroups = [];
        $resourceTotal = $allServices->count();
        $runningTotal = 0;
        foreach ($allServices as $service) {
            $productGroup = $service->product?->group;
            $group = $productGroup?->parent ?? $productGroup;
            $groupKey = $group ? (string) $group->id : 'ungrouped';
            $serviceGroups[$service->id] = $groupKey;
            $kind = $group ? $service->product?->type : null;
            [$icon, $tone] = match ($kind) {
                'nat_vm' => ['network', 'blue'],
                'cloud_vm' => ['cloud', 'blue'],
                'cdn' => ['globe', 'teal'],
                'bare_metal' => ['hardware-chip', 'amber'],
                'colocation' => ['building', 'teal'],
                'game_server' => ['terminal', 'violet'],
                default => ['layers', 'neutral'],
            };
            $groupSummaries[$groupKey] ??= [
                'key' => $groupKey,
                'name' => $group?->name ?? '未分组资源',
                'sort_order' => $group?->sort_order ?? PHP_INT_MAX,
                'icon' => $icon,
                'tone' => $tone,
                'total' => 0,
                'running' => 0,
            ];
            if ($groupSummaries[$groupKey]['icon'] !== $icon) {
                $groupSummaries[$groupKey]['icon'] = 'layers';
                $groupSummaries[$groupKey]['tone'] = 'neutral';
            }
            $running = $service->status === 'active' && ! data_get($service->specs, 'admin_deletion');
            $groupSummaries[$groupKey]['total']++;
            $groupSummaries[$groupKey]['running'] += (int) $running;
            $runningTotal += (int) $running;
        }
        uasort($groupSummaries, fn (array $a, array $b): int => [$a['sort_order'], (int) $a['key']] <=> [$b['sort_order'], (int) $b['key']]);
        $requestedGroup = $request->string('group')->toString();
        $selectedGroup = $groupSummaries[$requestedGroup] ?? null;
        $selectedGroupKey = $selectedGroup['key'] ?? '';
        $services = $allServices->filter(fn (Service $service): bool =>
            ($selectedGroupKey === '' || $serviceGroups[$service->id] === $selectedGroupKey)
            && ($status === '' || $service->status === $status)
        )->values();

        if ($resourceSearch !== '') {
            $services = $services->filter(fn ($service): bool => mb_stripos(implode(' ', [$service->name, $service->service_no, $service->ip_address, $service->nat_ip, $service->product?->name]), $resourceSearch) !== false)->values();
        }
        $serviceRenewalQuotes = [];
        foreach ($services as $listedService) {
            try {
                $serviceRenewalQuotes[$listedService->id] = app(\App\Services\Billing\RenewalService::class)->quote($listedService, $listedService->billing_cycle ?: 'monthly')['amount'];
            } catch (\Illuminate\Validation\ValidationException) {
                $serviceRenewalQuotes[$listedService->id] = null;
            }
        }

        return view('console.services.index', compact('services', 'status', 'groupSummaries', 'resourceTotal', 'runningTotal', 'selectedGroup', 'selectedGroupKey', 'resourceSearch', 'serviceRenewalQuotes'));
    }

    public function show(Request $request, Service $service, RenewalService $renewals, ControllerGateway $gateway): View|Response
    {
        $this->authorizeService($request, $service);
        if (\App\Support\Colocation::service($service)) {
            $encryptedPassword = data_get($service->specs, 'colocation.ssh.password_encrypted');

            return response()->view('console.services.colocation', [
                'service' => $service->load('product', 'user'),
                'adminView' => false,
                'sshPassword' => $encryptedPassword ? Crypt::decryptString($encryptedPassword) : null,
            ])->header('Cache-Control', 'private, no-store');
        }

        $service->load([
            'user',
            'product',
            'node',
            'natMappings' => fn ($query) => $query->latest(),
            'provisionTasks' => fn ($query) => $query->latest()->limit(6),
        ]);

        if ($service->product?->upstream_source === 'mofang') {
            $specialDetail = [];
            $syncError = null;
            try {
                $specialDetail = Cache::remember('mofang:service:'.substr((string) $service->external_ref, 7), 60, fn () => app(MofangGateway::class)->fetchService(substr((string) $service->external_ref, 7)));
                app(MofangServiceSync::class)->sync($service, $specialDetail);
            } catch (\Throwable $exception) {
                report($exception);
                $syncError = '上游详情暂时不可用，当前显示上次同步记录。';
            }
            $specialType = MofangServiceType::forService($service, $specialDetail);
            if (in_array($specialType, ['cdn', 'bare_metal'], true)) {
                return view($specialType === 'bare_metal' ? 'console.services.bare-metal' : 'console.services.upstream-special', $this->specialData($service, $specialDetail, $specialType, $renewals, false, $syncError));
            }
        }

        if ($service->product?->upstream_source === 'pterodactyl') {
            $renewalPrices = $renewals->pricesFor($service);
            $renewalQuotes = $renewalPrices->mapWithKeys(fn ($price) => [
                $price->billing_cycle => $renewals->quote($service, $price->billing_cycle)['amount'],
            ]);
            $panel = (array) data_get($service->specs, 'pterodactyl', []);

            return view('console.services.pterodactyl', [
                'service' => $service,
                'panelBaseUrl' => (string) data_get(PterodactylSettings::configuration(), 'base_url', 'https://pl.deviy.cn'),
                'panelServerId' => preg_replace('/^pterodactyl:/', '', (string) $service->external_ref),
                'panelEmail' => (string) ($panel['email'] ?? $service->user?->email),
                'panelPassword' => (string) ($panel['password'] ?? $service->user?->pterodactyl_password ?? ''),
                'panelNodeId' => (int) ($panel['node_id'] ?? 0),
                'renewalPrices' => $renewalPrices,
                'renewalQuotes' => $renewalQuotes,
                'cycles' => Billing::cycles(),
            ]);
        }

        $upstreamMonitors = [];
        $qzvpsPortRange = null;
        $qzvpsUsedPorts = [];
        $qzvpsAvailablePorts = [];
        if ($service->product?->upstream_source === 'qzvps') {
            $this->syncQzvpsNatMappings($service);
            $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
            if ($hostId > 0) {
                try {
                    $hostInfo = (array) (app(QzvpsClient::class)->hostInfo($hostId)['data'] ?? []);
                    $hostState = (int) ($hostInfo['state'] ?? 0);
                    $portMin = (int) ($hostInfo['min_port'] ?? 0);
                    $portMax = (int) ($hostInfo['max_port'] ?? 0);
                    if ($portMin > 0 && $portMax >= $portMin) {
                        $excludedPorts = array_values(array_filter(array_map(
                            'intval',
                            explode(',', (string) ($hostInfo['no_port'] ?? ''))
                        )));
                        $qzvpsPortRange = [
                            'min' => $portMin,
                            'max' => $portMax,
                            'excluded' => $excludedPorts,
                        ];
                    }
                    try {
                        $qzvpsAvailablePorts = app(QzvpsClient::class)->availablePorts($hostId);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                    if ($hostState === 2 && $service->provision_status === 'upstream_provisioning') {
                        $specs = is_array($service->specs) ? $service->specs : [];
                        $specs['qzvps']['os_name'] = (string) ($hostInfo['os_name'] ?? $service->os);
                        $specs['ostype'] = (string) ($hostInfo['os_name'] ?? ($specs['ostype'] ?? ''));
                        $service->update([
                            'status' => 'active',
                            'provision_status' => 'succeeded',
                            'os' => (string) ($hostInfo['os_name'] ?? $service->os),
                            'specs' => $specs,
                        ]);
                        $service->refresh();
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
            $qzvpsUsedPorts = $service->natMappings()
                ->pluck('external_port')
                ->map(fn ($port): int => (int) $port)
                ->filter(fn (int $port): bool => $port > 0)
                ->values()
                ->all();
            if ($hostId > 0) {
                try {
                    $metrics = Cache::remember('qzvps:metrics:'.$hostId, 60, fn () => app(QzvpsClient::class)->monitor($hostId));
                    $data = (array) data_get($metrics, 'data', []);
                    $rx = (float) data_get($data, 'NetworkStats.BytesReceivedPersec', 0);
                    $tx = (float) data_get($data, 'NetworkStats.BytesSentPersec', 0);
                    $upstreamMonitors = [
                        ['key' => 'cpu', 'unit' => '%', 'series' => [(float) data_get($data, 'CpuStats', 0)]],
                        ['key' => 'memory', 'unit' => '%', 'series' => [(float) data_get($data, 'MemoryStats', 0)]],
                        ['key' => 'bandwidth', 'unit' => 'Mbps', 'series' => [round(($rx + $tx) * 8 / 1024 / 1024, 3)]],
                    ];
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        } else {
            $this->syncNatMappings($service, $gateway);
        }

        $upstreamCharts = [];
        $upstreamTraffic = null;
        $upstreamReinstallOs = [];
        if ($service->product?->upstream_source === 'qzvps') {
            $qzvpsNodeId = (string) (data_get($service->specs, 'qzvps.node_id') ?: data_get($service->product?->metadata, 'qzvps.node_id', ''));
            $upstreamReinstallOs = collect(app(QzvpsCatalog::class)->imagesForNode($qzvpsNodeId))
                ->map(fn (array $image): array => [
                    'id' => (string) ($image['name'] ?? ''),
                    'name' => (string) ($image['name'] ?? ''),
                    'group' => ucfirst((string) ($image['type'] ?? '其他')),
                ])
                ->values()
                ->all();
        }
        if ($service->product?->upstream_source === 'mofang' && str_starts_with((string) $service->external_ref, 'mofang:')) {
            try {
                $upstreamGateway = app(MofangGateway::class);
                $upstreamRef = substr((string) $service->external_ref, 7);
                $upstreamDetail = Cache::remember('mofang:service:'.$upstreamRef, 60, fn () => $upstreamGateway->fetchService($upstreamRef));
                $this->syncUpstreamCredentials($service, $upstreamDetail);
                $service->refresh();
                $upstreamReinstallOs = (array) data_get($upstreamDetail, 'data.cloud_os', []);
                $metricsPayload = Cache::remember('mofang:metrics:'.$upstreamRef, 120, fn () => $upstreamGateway->fetchServiceMetrics($upstreamRef));
                $upstreamMonitors = $this->upstreamMonitors($metricsPayload);
                $upstreamCharts = (array) data_get($metricsPayload, 'charts', []);
                $upstreamTraffic = $this->upstreamTraffic(
                    $upstreamDetail,
                    (float) data_get($service->product?->metadata, 'markup_percent', config('mofang.default_markup_percent', 20)),
                );
                if ($upstreamTraffic) { $upstreamTraffic['packets']=app(\App\Services\Mofang\MofangTrafficOrders::class)->packets($service,$upstreamDetail); }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($service->product?->upstream_source !== 'mofang') {
            $sample = ServiceMetricSample::query()
                ->where('service_id', $service->id)
                ->latest('recorded_at')
                ->first();
            if ($sample) {
                $upstreamMonitors = [
                    ['key' => 'cpu', 'unit' => '%', 'series' => [$sample->cpu_percent]],
                    ['key' => 'memory', 'unit' => 'GB', 'series' => [$sample->memory_used_gb]],
                    ['key' => 'bandwidth', 'unit' => 'Mbps', 'series' => [$sample->net_rx_mbps]],
                ];
            }
        }

        $renewalPrices = $renewals->pricesFor($service);
        $renewalQuotes = $renewalPrices->mapWithKeys(fn ($price) => [
            $price->billing_cycle => $renewals->quote($service, $price->billing_cycle)['amount'],
        ]);

        return view('console.services.show', [
            'service' => $service,
            'renewalPrices' => $renewalPrices,
            'renewalQuotes' => $renewalQuotes,
            'upstreamMonitors' => $upstreamMonitors,
            'upstreamCharts' => $upstreamCharts,
            'upstreamTraffic' => $upstreamTraffic,
            'upstreamReinstallOs' => $upstreamReinstallOs,
            'qzvpsPortRange' => $qzvpsPortRange,
            'qzvpsUsedPorts' => $qzvpsUsedPorts,
            'qzvpsAvailablePorts' => $qzvpsAvailablePorts,
            'cycles' => Billing::cycles(),
            'pterodactylBaseUrl' => $service->product?->upstream_source === 'pterodactyl'
                ? (string) data_get(PterodactylSettings::configuration(), 'base_url', 'https://pl.deviy.cn')
                : '',
            'pterodactylServerId' => $service->product?->upstream_source === 'pterodactyl'
                ? preg_replace('/^pterodactyl:/', '', (string) $service->external_ref)
                : '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    /** @return array<string, mixed> */
    public function specialData(Service $service, array $detail, string $type, RenewalService $renewals, bool $adminView, ?string $syncError = null): array
    {
        $cdnData = null;
        if ($type === 'cdn') {
            try {
                $supplier = \App\Models\UpstreamSupplier::query()->where('driver', 'mofang')->where('status', 'active')->latest('id')->firstOrFail();
                $cdnData = (new MofangCartClient($supplier->base_url, $supplier->username, (string) $supplier->password, (bool) $supplier->verify_ssl))->cdnDetails(substr((string) $service->external_ref, 7));
            } catch (\Throwable $exception) {
                report($exception);
                $syncError = 'CDN 面板信息暂时同步失败，请稍后刷新；套餐与续费记录仍保留。';
            }
        }
        $bareMetrics = [];
        if ($type === 'bare_metal') {
            try {
                $bareMetrics = Cache::remember('mofang:bare-traffic:'.substr((string) $service->external_ref, 7), 120, fn () => app(MofangGateway::class)->fetchServiceMetrics(substr((string) $service->external_ref, 7)));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        $renewalPrices = $renewals->pricesFor($service);
        $renewalQuotes = $renewalPrices->mapWithKeys(fn ($price) => [$price->billing_cycle => $renewals->quote($service, $price->billing_cycle)['amount']]);

        return compact('service', 'cdnData', 'renewalPrices', 'renewalQuotes', 'syncError', 'adminView', 'bareMetrics') + ['upstreamDetail' => $detail, 'isCdn' => $type === 'cdn', 'reinstallOs' => array_values(array_filter((array) data_get($detail, 'data.cloud_os', []), 'is_array'))];
    }

    private function upstreamMonitors(array $payload): array
    {
        $data = is_array(data_get($payload, 'data')) ? data_get($payload, 'data') : $payload;
        if (is_array(data_get($data, 'host_data'))) {
            $data = array_replace($data, data_get($data, 'host_data'));
        }
        if (is_array(data_get($data, 'dcim'))) {
            $data['flow_packet_use_list'] ??= data_get($data, 'dcim.flow_packet_use_list');
            $data['flowpacket'] ??= data_get($data, 'dcim.flowpacket');
        }
        $definitions = [
            'cpu' => ['label' => 'CPU', 'unit' => '%', 'color' => '#2563eb', 'fill' => 'rgba(37,99,235,.10)'],
            'memory' => ['label' => '内存', 'unit' => '%', 'color' => '#7c3aed', 'fill' => 'rgba(124,58,237,.10)'],
            'disk' => ['label' => '磁盘', 'unit' => '%', 'color' => '#d97706', 'fill' => 'rgba(217,119,6,.10)'],
            'bandwidth' => ['label' => '带宽峰值', 'unit' => ' Mbps', 'color' => '#059669', 'fill' => 'rgba(5,150,105,.10)'],
        ];

        $units = (array) (data_get($payload, 'units') ?? data_get($data, 'units', []));
        $monitors = [];
        foreach ($definitions as $key => $definition) {
            $series = data_get($data, $key) ?? data_get($data, 'metrics.'.$key);
            if (is_numeric($series)) {
                $series = array_fill(0, 24, (float) $series);
            }
            if (! is_array($series) || $series === []) {
                continue;
            }

            $series = array_values(array_map(fn ($value): float => (float) $value, array_filter($series, 'is_numeric')));
            if ($series === []) {
                continue;
            }

            $unit = trim((string) ($units[$key] ?? '')) ?: $definition['unit'];
            $monitors[] = array_merge($definition, [
                'key' => $key,
                'unit' => $unit,
                'max' => $unit === '%' ? 100 : max(1, ceil(max($series) * 1.2)),
                'series' => $series,
            ]);
        }

        return $monitors;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function upstreamTraffic(array $payload, float $markupPercent = 0): ?array
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
        $remaining = data_get($data, 'flow_remaining') ?? data_get($data, 'traffic_remaining') ?? data_get($data, 'remaining_flow');
        $packets = data_get($data, 'flow_packet_use_list');
        if (! is_array($packets) || $packets === []) {
            $packets = data_get($data, 'flowpacket');
        }
        if ($total === null && $used === null && $remaining === null && ! is_array($packets)) {
            return null;
        }

        $total = is_numeric($total) ? (float) $total : null;
        $used = is_numeric($used) ? (float) $used : null;
        $remaining = is_numeric($remaining) ? (float) $remaining : ($total !== null && $used !== null ? max(0, $total - $used) : null);

        if (is_array($packets)) {
            $markupPercent = max(0, $markupPercent);
            $packets = array_values(array_map(function (array $packet) use ($markupPercent): array {
                $cost = round((float) ($packet['price'] ?? 0), 2);
                $packet['upstream_price'] = $cost;
                $packet['price'] = round($cost * (1 + $markupPercent / 100), 2);
                $packet['markup_percent'] = $markupPercent;

                return $packet;
            }, array_filter($packets, 'is_array')));
        }

        return compact('total', 'used', 'remaining', 'packets', 'markupPercent');
    }

    /** @param array<string, mixed> $payload */
    private function syncUpstreamCredentials(Service $service, array $payload): void
    {
        $host = (array) data_get($payload, 'data.host_data', []);
        if ($host === []) {
            return;
        }

        $remote = is_array(data_get($service->specs, 'remote')) ? data_get($service->specs, 'remote') : [];
        $address = trim((string) ($host['dedicatedip'] ?? $host['ip'] ?? ''));
        $username = trim((string) ($host['username'] ?? ''));
        $password = html_entity_decode((string) ($host['password'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $recentPasswordChange = (int) data_get($service->specs, 'remote.password_updated_at', 0) > now()->subMinutes(5)->timestamp;
        $os = (string) ($host['os'] ?? '');
        if ($address !== '') {
            $remote['address'] = $address;
        }
        if ($username !== '') {
            $remote['username'] = $username;
        }
        if ($password !== '' && ! $recentPasswordChange) {
            $remote['password'] = $password;
        }
        $remote['port'] = (int) ($host['port'] ?? $remote['port'] ?? 22);
        $remote['protocol'] = str_contains(strtolower($os), 'windows') ? 'rdp' : 'ssh';
        $specs = is_array($service->specs) ? $service->specs : [];
        $specs['remote'] = $remote;

        $rawIps = [];
        foreach (['dedicatedip', 'ip', 'ipv4', 'ipv6', 'assignedips', 'ips'] as $key) {
            $value = $host[$key] ?? null;
            foreach (is_array($value) ? $value : [$value] as $ip) {
                if (is_string($ip) && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    $rawIps[] = trim($ip);
                }
            }
        }
        $networkIps = array_values(array_unique($rawIps));
        $specs['network_ips'] = $networkIps;
        $specs['upstream'] = array_merge(is_array($specs['upstream'] ?? null) ? $specs['upstream'] : [], [
            'service_id' => (string) ($host['id'] ?? ''),
            'hostname' => (string) ($host['domain'] ?? ''),
            'ips' => $networkIps,
            'traffic_used_gb' => is_numeric($host['bwusage'] ?? null) ? (float) $host['bwusage'] : null,
            'traffic_limit_gb' => is_numeric($host['bwlimit'] ?? null) ? (float) $host['bwlimit'] : null,
        ]);

        $updates = ['specs' => $specs];
        $remoteStatus = strtolower((string) ($host['domainstatus'] ?? $host['status'] ?? ''));
        if (in_array($remoteStatus, ['active', 'running'], true)) {
            $updates['status'] = 'active';
            $updates['provision_status'] = 'success';
        } elseif (in_array($remoteStatus, ['pending', 'provisioning', 'creating'], true)) {
            $updates['status'] = 'provisioning';
            $updates['provision_status'] = 'upstream_provisioning';
        } elseif ($remoteStatus === 'suspended') {
            $updates['status'] = 'suspended';
            $updates['provision_status'] = 'upstream_suspended';
        } elseif (in_array($remoteStatus, ['cancelled', 'terminated', 'deleted'], true)) {
            $updates['status'] = 'terminated';
            $updates['provision_status'] = 'terminated';
        }
        if ($address !== '') {
            $updates['ip_address'] = $address;
        }
        if ($os !== '') {
            $updates['os'] = $os;
        }
        if (is_numeric($host['id'] ?? null)) {
            $updates['external_ref'] = 'mofang:'.$host['id'];
        }
        $service->update($updates);
    }

    private function syncQzvpsNatMappings(Service $service): void
    {
        if (! in_array($service->status, ['active', 'suspended', 'stopped', 'provisioning'], true) || data_get($service->specs, 'legacy_import.operation_hold', false)) {
            return;
        }
        try {
            app(QzvpsServiceSync::class)->sync($service);
        } catch (\Throwable $exception) {
            report($exception);
            $specs = (array) $service->specs;
            $specs['qzvps']['sync_error'] = '轻舟数据同步暂时失败，当前显示上次同步记录，请稍后刷新或联系客服。';
            $service->update(['specs' => $specs]);
        }
    }


    private function syncNatMappings(Service $service, ControllerGateway $gateway): void
    {
        try {
            app(ServiceNetworkSync::class)->sync($service);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function monitor(Request $request, Service $service): View|RedirectResponse
    {
        $this->authorizeService($request, $service);
        abort_if($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) === 'cdn', 404);


        if ($service->product?->upstream_source === 'mofang' && str_starts_with((string) $service->external_ref, 'mofang:')) {
            $ref = substr((string) $service->external_ref, 7);
            $gateway = app(MofangGateway::class);
            try {
                $payload = Cache::remember('mofang:metrics:'.$ref, 120, fn () => $gateway->fetchServiceMetrics($ref));
            } catch (\Throwable $exception) {
                report($exception);
                $payload = [];
            }
            $charts = (array) data_get($payload, 'charts', []);
        } else {
            $charts = $service->product?->upstream_source === 'qzvps'
                ? $this->qzvpsCharts($service)
                : $this->localServiceCharts($service);
        }

        return view('console.services.monitor', [
            'service' => $service,
            'upstreamCharts' => $charts,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function qzvpsCharts(Service $service): array
    {
        $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
        if ($hostId <= 0) {
            return [];
        }

        try {
            $payload = Cache::remember('qzvps:metrics:'.$hostId, 60, fn () => app(QzvpsClient::class)->monitor($hostId));
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }

        $data = (array) data_get($payload, 'data', []);
        $rx = (float) data_get($data, 'NetworkStats.BytesReceivedPersec', 0);
        $tx = (float) data_get($data, 'NetworkStats.BytesSentPersec', 0);
        $bandwidth = round(($rx + $tx) * 8 / 1024 / 1024, 3);
        $previous = now()->subMinute()->toDateTimeString();
        $current = now()->toDateTimeString();

        $series = function (string $name, float $value) use ($previous, $current): array {
            return [
                'name' => $name,
                'points' => [
                    ['time' => $previous, 'value' => $value],
                    ['time' => $current, 'value' => $value],
                ],
            ];
        };

        return [
            'cpu' => [
                'title' => 'CPU 使用率',
                'unit' => '%',
                'series' => [$series('CPU', (float) data_get($data, 'CpuStats', 0))],
            ],
            'memory' => [
                'title' => '内存使用率',
                'unit' => '%',
                'series' => [$series('内存', (float) data_get($data, 'MemoryStats', 0))],
            ],
            'bandwidth' => [
                'title' => '网络带宽',
                'unit' => 'Mbps',
                'series' => [$series('带宽', $bandwidth)],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function localServiceCharts(Service $service): array
    {
        try {
            app(MetricCollector::class)->collectService($service);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $samples = ServiceMetricSample::query()
            ->where('service_id', $service->id)
            ->where('recorded_at', '>=', now()->subDays(30))
            ->oldest('recorded_at')
            ->get();

        if ($samples->isEmpty()) {
            return [];
        }

        $point = fn (ServiceMetricSample $sample, string $field): array => [
            'time' => $sample->recorded_at->format('Y-m-d H:i:s'),
            'value' => (float) $sample->{$field},
        ];

        return [
            'cpu' => [
                'title' => 'CPU使用量',
                'unit' => '%',
                'series' => [['name' => 'CPU使用率', 'points' => $samples->map(fn ($sample) => $point($sample, 'cpu_percent'))->all()]],
            ],
            'memory' => [
                'title' => '内存用量',
                'unit' => 'GB',
                'series' => [['name' => '内存用量', 'points' => $samples->map(fn ($sample) => $point($sample, 'memory_used_gb'))->all()]],
            ],
            'disk' => [
                'title' => '磁盘IO',
                'unit' => 'MB/s',
                'series' => [
                    ['name' => '读取速度', 'points' => $samples->map(fn ($sample) => $point($sample, 'disk_read_mbps'))->all()],
                    ['name' => '写入速度', 'points' => $samples->map(fn ($sample) => $point($sample, 'disk_write_mbps'))->all()],
                ],
            ],
            'bandwidth' => [
                'title' => '网卡',
                'unit' => 'Mbps',
                'series' => [
                    ['name' => '进', 'points' => $samples->map(fn ($sample) => $point($sample, 'net_rx_mbps'))->all()],
                    ['name' => '出', 'points' => $samples->map(fn ($sample) => $point($sample, 'net_tx_mbps'))->all()],
                ],
            ],
        ];
    }

    public function rdpFile(Request $request, Service $service): Response
    {
        $this->authorizeService($request, $service);
        abort_if($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) === 'cdn', 404);


        $remote = is_array(data_get($service->specs, 'remote')) ? data_get($service->specs, 'remote') : [];
        $mapping = $service->natMappings->first();
        $address = trim((string) ($remote['address'] ?? '')) ?: trim((string) ($mapping?->external_ip ?: $service->nat_ip ?: $service->ip_address));
        if ($address === '') {
            abort(404, '远程地址尚未同步。');
        }

        $endpoint = RemoteEndpoint::forService($service);
        $address = $endpoint['host'];
        $port = $endpoint['port'];
        $username = trim((string) ($remote['username'] ?? '')) ?: 'Administrator';
        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $service->service_no) ?: 'deviy-remote';

        $content = implode("\r\n", [
            'full address:s:'.$endpoint['display'],
            'username:s:'.$username,
            'prompt for credentials:i:1',
            'authentication level:i:2',
            'screen mode id:i:2',
            'use multimon:i:0',
            'redirectclipboard:i:1',
        ])."\r\n";

        return response($content, 200, [
            'Content-Type' => 'application/x-rdp; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.rdp"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function vnc(Request $request, Service $service, ControllerGateway $gateway): View|RedirectResponse
    {
        $this->authorizeService($request, $service);
        abort_if($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) === 'cdn', 404);


        if ($service->product?->upstream_source === 'mofang') {
            $ref = (string) $service->external_ref;
            if (! str_starts_with($ref, 'mofang:')) {
                return back()->withErrors(['vnc' => '实例 ID 尚未同步，暂时无法连接 VNC。']);
            }

            try {
                $ticket = app(MofangGateway::class)
                    ->fetchVnc(substr($ref, 7));
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['vnc' => 'VNC 连接创建失败：'.$exception->getMessage()]);
            }

            $vncUrl = (string) (data_get($ticket, 'data.url') ?? data_get($ticket, 'url') ?? '');
            if ($vncUrl === '') {
                return back()->withErrors(['vnc' => '没有返回 VNC 连接地址。']);
            }

            return view('console.services.vnc', [
                'service' => $service,
                'vncUrl' => $vncUrl,
            ]);
        }

        if ($service->product?->upstream_source === 'qzvps') {
            $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
            if ($hostId <= 0) {
                return back()->withErrors(['vnc' => '该服务尚未绑定轻舟主机，暂时无法连接 VNC。']);
            }

            return view('console.services.vnc', [
                'service' => $service,
                'vncUrl' => app(QzvpsClient::class)->consoleUrl($hostId),
            ]);
        }

        $externalRef = trim((string) $service->external_ref);
        $vmid = str_starts_with($externalRef, 'id:') ? substr($externalRef, 3) : $externalRef;
        if ($vmid === '') {
            return back()->withErrors(['vnc' => '该服务还没有创建主控实例，暂时无法连接 VNC。']);
        }

        $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
        if ($nodeId === '') {
            return back()->withErrors(['vnc' => '该服务未绑定主控节点，暂时无法连接 VNC。']);
        }

        try {
            $ticket = $gateway->vncTicket($nodeId, $vmid);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['vnc' => 'VNC 连接创建失败：'.$exception->getMessage()]);
        }

        $vncPath = (string) ($ticket['url'] ?? '');
        if ($vncPath === '') {
            return back()->withErrors(['vnc' => '主控没有返回 VNC 连接地址。']);
        }

        return view('console.services.vnc', [
            'service' => $service,
            'vncUrl' => $gateway->publicUrl().$vncPath,
        ]);
    }

    public function renew(Request $request, Service $service, RenewalService $renewals): RedirectResponse
    {
        $this->authorizeService($request, $service);

        $data = $request->validate([
            'billing_cycle' => ['required', 'string', Rule::in(array_keys(Billing::cycles()))],
        ]);

        $order = $renewals->createOrder($service, $data['billing_cycle']);

        return redirect()
            ->route('console.orders.show', $order)
            ->with('status', '续费订单已创建，请完成支付。');
    }


    public function buyTraffic(Request $request, Service $service): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        $this->authorizeService($request,$service);
        abort_unless($service->product?->upstream_source==='mofang'&&in_array($service->status,['active','stopped','suspended'],true)&&str_starts_with((string)$service->external_ref,'mofang:'),422,'实例尚不可订购流量包。');
        $data=$request->validate(['flow_packet_id'=>['required','integer','min:1']]);
        $detail=app(MofangGateway::class)->fetchTraffic(substr($service->external_ref,7));$orders=app(\App\Services\Mofang\MofangTrafficOrders::class);
        $packet=collect($orders->packets($service,$detail))->firstWhere('id',(int)$data['flow_packet_id']);abort_unless(is_array($packet),422,'所选流量包不属于当前实例。');
        $order=$orders->create($service,$packet);
        return redirect()->route('console.orders.show',$order)->with('status','流量包订单已创建，支付后提交机房交付。');
    }

    public function changePassword(Request $request, Service $service, ControllerGateway $gateway): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        $this->authorizeService($request, $service);
        if ($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) === 'cdn') {
            return back()->withErrors(['service' => 'CDN 服务请通过 CDN 面板管理，不支持云服务器操作。']);
        }


        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'force_shutdown' => ['nullable', 'boolean'],
        ]);

        if ($service->product?->upstream_source === 'mofang') {
            $ref = (string) $service->external_ref;
            if (! str_starts_with($ref, 'mofang:')) {
                return back()->withErrors(['password' => '实例 ID 尚未同步。']);
            }
            if (! (bool) ($data['force_shutdown'] ?? false)) {
                return back()->withErrors(['password' => '请先同意强制关机后再重置密码。']);
            }
            try {
                app(MofangGateway::class)->performServiceAction(substr($ref, 7), 'crack_pass', [
                    'password' => $data['password'],
                    'force_shutdown' => true,
                ]);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['password' => '重置密码失败：'.$exception->getMessage()]);
            }
            $specs = is_array($service->specs) ? $service->specs : [];
            $specs['remote'] = array_merge(is_array($specs['remote'] ?? null) ? $specs['remote'] : [], [
                'password' => $data['password'],
                'password_updated_at' => now()->timestamp,
            ]);
            $service->update(['specs' => $specs]);
            Cache::forget('mofang:service:'.$ref);

            return back()->with('status', '远程密码已同步。');
        }

        if ($service->product?->upstream_source === 'qzvps') {
            if (data_get($service->specs, 'legacy_import.operation_hold', false)) {
                return back()->withErrors(['password' => '该实例正在核查，暂不可修改密码。']);
            }
            $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
            try {
                app(QzvpsClient::class)->resetPassword($hostId, $data['password']);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['password' => '轻舟重置密码失败，请稍后重试。']);
            }
            $specs = (array) $service->specs;
            $specs['remote']['password'] = $data['password'];
            $service->update(['specs' => $specs]);

            return back()->with('status', '远程密码已更新并同步到轻舟。');
        }

        $nodeId = (string) (data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id);
        $ref = trim((string) $service->external_ref);
        $vmid = str_starts_with($ref, 'vmid:') ? substr($ref, 5) : (str_starts_with($ref, 'id:') ? substr($ref, 3) : $ref);
        $internalIp = trim((string) data_get($service->specs, 'internal_ip'));
        if ($internalIp === '') {
            $internalIp = (string) $service->natMappings->first()?->internal_ip;
        }
        $remote = is_array(data_get($service->specs, 'remote')) ? data_get($service->specs, 'remote') : [];
        $username = trim((string) ($remote['username'] ?? '')) ?: (str_contains(strtolower((string) $service->os), 'windows') ? 'Administrator' : 'root');

        if ($nodeId === '' || $vmid === '' || $internalIp === '') {
            return back()->withErrors(['password' => '主控实例信息尚未同步，暂时无法修改密码。']);
        }

        try {
            $gateway->changeGuestPassword($nodeId, $vmid, $internalIp, $username, $data['password']);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['password' => '主控修改密码失败：'.$exception->getMessage()]);
        }

        $specs = is_array($service->specs) ? $service->specs : [];
        $specs['remote'] = array_merge($remote, [
            'username' => $username,
            'password' => $data['password'],
        ]);
        $service->update(['specs' => $specs]);

        return back()->with('status', '远程密码已更新并同步到主控。');
    }

    public function autoRenew(Request $request, Service $service): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        $this->authorizeService($request, $service);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $service->update(['auto_renew' => (bool) $data['enabled']]);

        return back()->with('status', $data['enabled'] ? '已开启自动续费。' : '已关闭自动续费。');
    }

    public function action(Request $request, Service $service, ResourceCleanupService $cleanup): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        $this->authorizeService($request, $service);
        if ($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) === 'cdn') {
            return back()->withErrors(['service' => 'CDN 服务请通过 CDN 面板管理，不支持云服务器操作。']);
        }


        $data = $request->validate([
            'action' => ['required', 'in:start,stop,reboot,reinstall'],
        ]);

        if ($service->product?->upstream_source === 'qzvps') {
            $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
            if ($hostId <= 0) {
                return back()->withErrors(['action' => '实例尚未绑定轻舟主机 ID。']);
            }

            try {
                if ($data['action'] === 'reinstall') {
                    $os = (string) $request->validate(['os' => ['required', 'string', 'max:80']])['os'];
                    $allowed = collect(app(QzvpsCatalog::class)->imagesForNode((string) (data_get($service->specs, 'qzvps.node_id') ?: data_get($service->product?->metadata, 'qzvps.node_id', ''))))
                        ->pluck('name')
                        ->all();
                    if ($allowed !== [] && ! in_array($os, $allowed, true)) {
                        return back()->withErrors(['os' => '所选系统不属于该轻舟宿主机可用系统。']);
                    }
                    app(QzvpsClient::class)->resetOs($hostId, $os, '');
                } else {
                    app(QzvpsClient::class)->action($hostId, match ($data['action']) {
                        'start' => 'start',
                        'stop' => 'shutdown',
                        'reboot' => 'reboot',
                    });
                }
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['action' => '轻舟操作失败：'.$exception->getMessage()]);
            }

            $service->update([
                'status' => match ($data['action']) {
                    'stop' => 'stopped',
                    'reinstall' => 'provisioning',
                    default => 'active',
                },
                'provision_status' => $data['action'] === 'reinstall' ? 'upstream_provisioning' : 'success',
            ]);

            return back()->with('status', $data['action'] === 'reinstall' ? '轻舟已进入重装队列。' : '轻舟命令已执行。');
        }

        if ($service->product?->upstream_source === 'mofang') {
            $ref = (string) $service->external_ref;
            if (! str_starts_with($ref, 'mofang:')) {
                return back()->withErrors(['action' => '实例 ID 尚未同步，请稍后再试。']);
            }

            try {
                $payload = [];
                if ($data['action'] === 'reinstall') {
                    $payload['os'] = $request->validate(['os' => ['required', 'integer', 'min:1']])['os'];
                    $detail = app(MofangGateway::class)->fetchService(substr($ref, 7));
                    $allowedOs = collect((array) data_get($detail, 'data.cloud_os', []))
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();
                    if (! in_array((int) $payload['os'], $allowedOs, true)) {
                        return back()->withErrors(['os' => '所选操作系统不属于该实例可用系统列表。']);
                    }
                }
                $result = app(MofangGateway::class)
                    ->performServiceAction(substr($ref, 7), $data['action'], $payload);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['action' => '操作失败：'.$exception->getMessage()]);
            }

            $service->update([
                'status' => match ($data['action']) {
                    'stop' => 'stopped',
                    'reinstall' => 'provisioning',
                    default => 'active',
                },
                'provision_status' => $data['action'] === 'reinstall' ? 'upstream_provisioning' : 'success',
            ]);

            return back()->with('status', '操作已提交：'.match ($data['action']) {
                'start' => '开机',
                'stop' => '关机',
                'reboot' => '重启',
                'reinstall' => '重装系统',
            });
        }

        $nextStatus = match ($data['action']) {
            'start' => 'active',
            'stop' => 'stopped',
            'reboot' => $service->status,
            'reinstall' => 'provisioning',
        };

        if (in_array($service->status, ['terminated', 'suspended'], true) && $data['action'] !== 'start') {
            return back()->withErrors(['action' => '当前服务状态不允许该操作。']);
        }

        $service->update(['status' => $nextStatus, 'provision_status' => 'queued']);

        if ($data['action'] === 'terminate') {
            $cleanup->releaseForTermination($service);
        }

        ProvisionTask::create([
            'service_id' => $service->id,
            'operation' => $data['action'],
            'status' => 'queued',
            'payload' => ['source' => 'console', 'requested_by' => $request->user()->id],
        ]);

        return back()->with('status', '操作已提交，主控将在数秒内下发到节点。');
    }

    public function storeMapping(Request $request, Service $service): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        $this->authorizeService($request, $service);
        if ($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) !== 'nat_vm') {
            return back()->withErrors(['mapping' => '独立 IP、裸金属和 CDN 产品不使用端口映射。']);
        }


        if ($service->product?->upstream_source === 'qzvps') {
            if (data_get($service->specs, 'legacy_import.operation_hold', false) || ! in_array($service->status, ['active', 'suspended', 'stopped'], true)) {
                return back()->withErrors(['mapping' => '该实例正在核查或已结束，暂不可管理端口映射。']);
            }
            try {
                app(QzvpsServiceSync::class)->sync($service);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['mapping' => '无法读取轻舟实例和映射数据，未执行端口操作，请稍后重试。']);
            }
        }

        if (! $service->nat_ip && $this->controllerNodeId($service) === '') {
            return back()->withErrors(['mapping' => '该实例不是 NAT 云机，无法添加端口映射。']);
        }

        $data = $request->validate([
            'external_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'internal_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['required', 'in:tcp,udp,tcp+udp'],
        ]);

        $hostId = 0;
        if ($service->product?->upstream_source === 'qzvps') {
            $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
            if ($hostId <= 0) {
                return back()->withErrors(['mapping' => '实例尚未绑定轻舟主机 ID。']);
            }

            try {
                $hostInfo = (array) (app(QzvpsClient::class)->hostInfo($hostId)['data'] ?? []);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['mapping' => '无法核对轻舟可用端口范围，未执行添加操作。']);
            }

            $portMin = (int) ($hostInfo['min_port'] ?? 0);
            $portMax = (int) ($hostInfo['max_port'] ?? 0);
            if ($portMin > 0 && $portMax >= $portMin) {
                $externalPort = (int) $data['external_port'];
                if ($externalPort < $portMin || $externalPort > $portMax) {
                    return back()->withErrors([
                        'external_port' => '外网端口必须在 '.$portMin.'–'.$portMax.' 范围内。',
                    ]);
                }

                $excludedPorts = array_filter(array_map(
                    'intval',
                    explode(',', (string) ($hostInfo['no_port'] ?? ''))
                ));
                if (in_array($externalPort, $excludedPorts, true)) {
                    return back()->withErrors([
                        'external_port' => '该端口已被主控保留，请选择其他端口。',
                    ]);
                }
            }

            if (NatMapping::query()->where('service_id', $service->id)->where('external_port', $data['external_port'])->exists()) {
                return back()->withErrors(['external_port' => '该端口已在本实例中使用，请更换一个端口。']);
            }
        }

        $internalIp = trim((string) data_get($service->specs, 'internal_ip'));
        if ($internalIp === '') {
            $internalIp = (string) $service->natMappings->first()?->internal_ip;
        }
        if ($internalIp === '') {
            return back()->withErrors(['mapping' => '主控尚未返回实例内网地址，请稍后再试。']);
        }

        $controllerNodeId = $this->controllerNodeId($service);
        if ($controllerNodeId !== '' && $service->product?->upstream_source !== 'qzvps') {
            $gateway = app(ControllerGateway::class);
            $externalIp = (string) ($service->nat_ip ?: $service->node?->host);
            if ($externalIp === '') {
                return back()->withErrors(['mapping' => '主控尚未返回实例公网地址，请稍后再试。']);
            }

            try {
                $nodeInfo = (array) ($gateway->node($controllerNodeId) ?? []);
                $portMin = (int) ($nodeInfo['port_min'] ?? 0);
                $portMax = (int) ($nodeInfo['port_max'] ?? 0);
                if ($portMin > 0 && $portMax >= $portMin && ((int) $data['external_port'] < $portMin || (int) $data['external_port'] > $portMax)) {
                    return back()->withErrors(['external_port' => '外网端口必须在 '.$portMin.'–'.$portMax.' 范围内。']);
                }

                $rules = collect($gateway->natRules($controllerNodeId));
                $requestedProtocols = $data['protocol'] === 'tcp+udp' ? ['tcp', 'udp'] : [$data['protocol']];
                $conflict = $rules->contains(function (array $rule) use ($externalIp, $data, $requestedProtocols): bool {
                    $existingProtocols = strtolower((string) ($rule['protocol'] ?? 'tcp')) === 'all'
                        ? ['tcp', 'udp']
                        : [strtolower((string) ($rule['protocol'] ?? 'tcp'))];

                    return (string) ($rule['external_ip'] ?? '') === $externalIp
                        && (int) ($rule['external_port'] ?? 0) === (int) $data['external_port']
                        && array_intersect($requestedProtocols, $existingProtocols) !== [];
                });
                if ($conflict) {
                    return back()->withErrors(['external_port' => '该端口已被占用，请更换一个端口。']);
                }

                foreach ($requestedProtocols as $protocol) {
                    $gateway->createNatRule($controllerNodeId, [
                        'external_ip' => $externalIp,
                        'external_port' => (int) $data['external_port'],
                        'internal_ip' => $internalIp,
                        'internal_port' => (int) $data['internal_port'],
                        'protocol' => $protocol,
                        'status' => 'active',
                    ]);
                }
                $gateway->applyNetwork($controllerNodeId, 'nat');
                app(ServiceNetworkSync::class)->sync($service);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['mapping' => '主控添加端口映射失败：'.$exception->getMessage()]);
            }

            return back()->with('status', '主控端口映射已添加。');
        }

        if ($service->product?->upstream_source === 'qzvps' && (int) data_get($service->specs, 'qzvps.port_num') > 0 && $service->natMappings->count() >= (int) data_get($service->specs, 'qzvps.port_num')) {
            return back()->withErrors(['mapping' => '端口映射数量已达到该实例配额。']);
        }

        if ($service->product?->upstream_source === 'qzvps') {
            try {
                app(QzvpsClient::class)->addNatAcl($hostId, [
                    'name' => 'port-'.$data['external_port'],
                    'sport' => $data['external_port'],
                    'dport' => $data['internal_port'],
                    'type' => $data['protocol'] === 'tcp+udp' ? 'all' : $data['protocol'],
                ]);
                $this->syncQzvpsNatMappings($service);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['mapping' => '轻舟添加端口映射失败：'.$exception->getMessage()]);
            }

            return back()->with('status', '轻舟端口映射已添加。');
        }

        $requestedProtocols = $data['protocol'] === 'tcp+udp' ? ['tcp', 'udp'] : [$data['protocol']];
        $conflict = NatMapping::query()
            ->where('external_ip', $service->nat_ip)
            ->where('external_port', $data['external_port'])
            ->get()
            ->contains(function (NatMapping $mapping) use ($requestedProtocols): bool {
                $existing = $mapping->protocol === 'tcp+udp' ? ['tcp', 'udp'] : [$mapping->protocol];

                return array_intersect($requestedProtocols, $existing) !== [];
            });

        if ($conflict) {
            return back()->withErrors(['external_port' => '该端口已被占用，请更换一个端口。']);
        }

        NatMapping::create([
            'service_id' => $service->id,
            'external_ip' => $service->nat_ip,
            'external_port' => $data['external_port'],
            'internal_ip' => $internalIp,
            'internal_port' => $data['internal_port'],
            'protocol' => $data['protocol'],
            'status' => 'pending',
        ]);

        ProvisionTask::create([
            'service_id' => $service->id,
            'operation' => 'sync_nat',
            'status' => 'queued',
            'payload' => ['source' => 'console', 'requested_by' => $request->user()->id],
        ]);

        return back()->with('status', '端口映射已添加，正在下发到节点。');
    }

    public function destroyMapping(Request $request, Service $service, NatMapping $mapping): RedirectResponse
    {
        abort_if(data_get($service->specs, 'admin_deletion'), 422, '该服务正在核对销毁结果，暂不接受运维与付费操作。');
        $this->authorizeService($request, $service);
        if ($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) !== 'nat_vm') {
            return back()->withErrors(['mapping' => '独立 IP、裸金属和 CDN 产品不使用端口映射。']);
        }

        abort_unless($mapping->service_id === $service->id, 404);

        if ($service->product?->upstream_source === 'qzvps') {
            if (data_get($service->specs, 'legacy_import.operation_hold', false) || ! in_array($service->status, ['active', 'suspended', 'stopped'], true)) {
                return back()->withErrors(['mapping' => '该实例正在核查或已结束，暂不可管理端口映射。']);
            }
            $hostId = (int) preg_replace('/^qzvps:/', '', (string) $service->external_ref);
            try {
                foreach (app(QzvpsClient::class)->natAclList($hostId) as $rule) {
                    if ((int) ($rule['sport'] ?? $rule['external_port'] ?? 0) === (int) $mapping->external_port
                        && (int) ($rule['dport'] ?? $rule['internal_port'] ?? 0) === (int) $mapping->internal_port
                        && (string) ($rule['dip'] ?? $rule['internal_ip'] ?? '') === $mapping->internal_ip) {
                        $ruleId = (int) ($rule['id'] ?? $rule['acl_id'] ?? 0);
                        if ($ruleId > 0) {
                            app(QzvpsClient::class)->removeNatAcl($hostId, $ruleId);
                        }
                    }
                }
                app(QzvpsServiceSync::class)->sync($service);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['mapping' => '轻舟删除或刷新映射失败，请刷新列表核对；本地记录未强行删除。']);
            }

            return back()->with('status', '轻舟端口映射已删除并同步。');
        }

        $controllerNodeId = $this->controllerNodeId($service);
        if ($controllerNodeId !== '') {
            try {
                $gateway = app(ControllerGateway::class);
                foreach ($gateway->natRules($controllerNodeId) as $rule) {
                    if ((int) ($rule['external_port'] ?? 0) === (int) $mapping->external_port
                        && (string) ($rule['internal_ip'] ?? '') === (string) $mapping->internal_ip
                        && (int) ($rule['internal_port'] ?? 0) === (int) $mapping->internal_port) {
                        $ruleId = (string) ($rule['id'] ?? '');
                        if ($ruleId !== '') {
                            $gateway->deleteNatRule($controllerNodeId, $ruleId);
                        }
                    }
                }
                $gateway->applyNetwork($controllerNodeId, 'nat');
                app(ServiceNetworkSync::class)->sync($service);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['mapping' => '主控删除端口映射失败：'.$exception->getMessage()]);
            }

            return back()->with('status', '主控端口映射已删除。');
        }

        $mapping->delete();

        ProvisionTask::create([
            'service_id' => $service->id,
            'operation' => 'sync_nat',
            'status' => 'queued',
            'payload' => ['source' => 'console', 'requested_by' => $request->user()->id],
        ]);

        return back()->with('status', '端口映射已删除，正在同步节点。');
    }

    private function controllerNodeId(Service $service): string
    {
        $nodeId = (string) data_get($service->product?->metadata, 'controller.node_id', '');
        if ($nodeId !== '') {
            return $nodeId;
        }

        return $service->node?->agent_mode === 'controller'
            ? (string) $service->node?->agent_node_id
            : '';
    }

    private function authorizeService(Request $request, Service $service): void
    {
        abort_unless($service->user_id === $request->user()->id, 404);
        abort_if(\App\Support\Colocation::service($service) && ! $request->routeIs('console.services.show'), 422, '托管设备由人工维护，请通过工单联系机房。');
    }
}
