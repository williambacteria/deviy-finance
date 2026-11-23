@extends('layouts.console')

@section('title', $service->name)
@section('page-title', '实例详情')

@section('content')
@include('partials.service-workspace-style')
<div class="dv-service-workspace" data-service-workspace>


    @php
        $isUpstream = $service->product?->upstream_source === 'mofang';
        $isPterodactyl = $service->product?->upstream_source === 'pterodactyl';
        $isQzvps = $service->product?->upstream_source === 'qzvps';
        $uplinkBandwidth = (int) (data_get($service->specs, 'upload_mbps') ?: data_get($service->specs, 'bandwidth_mbps') ?: $service->bandwidth_mbps);
        $isNatInstance = $isQzvps ? (bool) data_get($service->specs, 'qzvps.is_nat') : (bool) ($service->nat_ip || $service->product?->type === 'nat_vm' || data_get($service->product?->metadata, 'controller.network_mode') === 'private' || data_get($service->specs, 'qzvps.is_nat'));
        $isUpstreamNat = $isNatInstance;
    @endphp
    @if (data_get($service->specs, 'qzvps.sync_error'))
        <div class="note warn" style="margin-bottom:16px">{{ data_get($service->specs, 'qzvps.sync_error') }}</div>
    @endif
    <style>
        .b-mapping-panel { min-width: 0; max-width: 100%; }
        .b-mapping-panel .table-wrap { max-width: 100%; overflow-x: auto; }
        .b-mapping-panel .mapping-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
        .mapping-form .mapping-field { flex: 1 1 150px; min-width: 0; }
        .mapping-form .mapping-field .input, .mapping-form .mapping-field .select { width: 100%; }
        .mapping-port-label { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
        .mapping-port-label .form-label { margin: 0; }
        .mapping-help-wrap { display: inline-flex; }
        .mapping-help-toggle { display: inline-grid; place-items: center; width: 26px; height: 26px; flex: 0 0 26px; padding: 0; border: 1px solid var(--ui-line); border-radius: 50%; background: var(--ui-soft); color: var(--ui-blue); font: inherit; font-weight: 700; cursor: help; }
        .mapping-help-toggle:hover, .mapping-help-toggle[aria-expanded="true"] { border-color: var(--ui-blue); }
        .mapping-help-toggle:focus-visible { outline: 2px solid var(--ui-blue); outline-offset: 2px; }
        .mapping-help-content { position: fixed; z-index: 100; width: min(300px, calc(100vw - 32px)); min-width: min(250px, calc(100vw - 32px)); max-height: calc(100vh - 32px); overflow: auto; padding: 10px 12px; border: 1px solid var(--ui-line); border-radius: 8px; background: var(--ui-bg); color: var(--ui-text); font-size: 13px; line-height: 1.6; overflow-wrap: anywhere; box-shadow: 0 10px 28px rgba(15,23,42,.2); }
        .mapping-port-input-wrap { position: relative; }
        .mapping-port-suggestions { position: absolute; inset: calc(100% + 4px) 0 auto; z-index: 20; max-height: 180px; overflow: auto; padding: 4px; border: 1px solid var(--ui-line); border-radius: 8px; background: var(--ui-bg); box-shadow: 0 8px 20px rgba(15,23,42,.16); }
        .mapping-port-suggestions button { display: block; width: 100%; padding: 6px 8px; border: 0; border-radius: 6px; background: transparent; color: var(--ui-text); text-align: left; font: inherit; cursor: pointer; }
        .mapping-port-suggestions button:hover, .mapping-port-suggestions button:focus-visible { background: var(--ui-soft); }
        .mapping-help-content[hidden], .mapping-port-suggestions[hidden] { display: none; }
        @media (max-width: 700px) {
            .mapping-form > .btn { width: 100%; }
        }
    </style>
    <div class="b-instance-hero">
        <div class="b-hero-top">
            <div class="b-hero-identity">
                <div class="b-hero-logo"><x-icon name="server" :size="24" /></div>
                <div>
                    <h1>{{ $service->product?->name ?? $service->name }}</h1>
                    <p>{{ $service->service_no }} · {{ $service->os ?: '系统待定' }} · {{ $isUpstreamNat ? 'NAT 模式' : '独立 IP' }}</p>
                </div>
            </div>
            <x-status :status="$service->status" class="b-hero-status" />
        </div>
        <div class="b-hero-actions">
            <a class="btn btn-hero" href="{{ route('console.services.index') }}">返回列表</a>
            @if (! $isPterodactyl)
                <a class="btn btn-hero-primary" href="{{ route('console.services.vnc', $service) }}" rel="noopener" data-vnc-popup="{{ $service->id }}">VNC 控制台</a>
                <a class="btn btn-hero" href="{{ route('console.services.monitor', $service) }}">资源监控</a>
            @elseif ($pterodactylServerId !== '')
                <a class="btn btn-hero-primary" href="{{ rtrim($pterodactylBaseUrl, '/') }}/server/{{ $pterodactylServerId }}" target="_blank" rel="noopener">打开游戏面板</a>
            @endif
            <a class="btn btn-hero" href="{{ route('console.tickets.create') }}">提交工单</a>
        </div>
    </div>

    @php
        $monitorLatest = collect($upstreamMonitors)->mapWithKeys(function (array $monitor): array {
            return [$monitor['key'] => ['value' => end($monitor['series']) ?: 0, 'unit' => $monitor['unit'] ?? '']];
        });
        $cpuLatest = $monitorLatest->get('cpu');
        $memoryLatest = $monitorLatest->get('memory');
        $bandwidthLatest = $monitorLatest->get('bandwidth');
    @endphp
    <div class="b-stat-grid">
        <div class="b-stat-card">
            <span>CPU / 内存</span>
            <b>{{ $cpuLatest ? number_format((float) $cpuLatest['value'], 0).'%' : '—' }} / {{ $memoryLatest ? number_format((float) $memoryLatest['value'], 1).$memoryLatest['unit'] : '—' }}</b>
            <small>{{ $service->specs['cpu'] ?? 2 }} 核 / {{ $service->specs['memory_gb'] ?? 4 }} GB</small>
        </div>
        <div class="b-stat-card">
            <span>带宽峰值</span>
            <b>{{ $bandwidthLatest ? number_format((float) $bandwidthLatest['value'], 1).$bandwidthLatest['unit'] : '—' }}</b>
            <small>上行带宽 {{ $uplinkBandwidth }} Mbps</small>
        </div>
        <div class="b-stat-card">
            <span>本月流量</span>
            <b>{{ $upstreamTraffic && $upstreamTraffic['used'] !== null ? number_format((float) $upstreamTraffic['used'], 2).' GB' : '—' }}</b>
            <small>{{ $upstreamTraffic && $upstreamTraffic['remaining'] !== null ? '剩余 '.number_format((float) $upstreamTraffic['remaining'], 2).' GB' : '流量包按需订购' }}</small>
        </div>
        <div class="b-stat-card">
            <span>到期时间</span>
            <b>{{ $service->expires_at?->format('Y-m-d') ?? '无限期' }}</b>
            <small>@if ($service->expires_at){{ $service->expires_at->format('H:i') }} 到期 @else无限期 @endif · {{ $service->auto_renew ? '自动续费已开启' : '自动续费未开启' }}</small>
        </div>
    </div>

    <div class="b-detail-layout">
    <div class="grid-2 b-detail-contents">
        <div class="panel b-left-panel" style="align-self:start">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="server" :size="20" /></span><span class="dv-card-caption"><span>实例信息</span><small>查看当前实例与资源配置</small></span></h2><span class="meta">最后同步 {{ $service->updated_at?->diffForHumans() }}</span></div>
            <div class="panel-body instance-body">
                <div class="instance-info-grid">
                    <div class="instance-field"><span>实例编号</span><b class="mono">{{ $service->service_no }}</b></div>
                    <div class="instance-field"><span>产品</span><b class="mono">{{ $service->product?->name ?? '—' }}</b></div>
                    <div class="instance-field"><span>操作系统</span><b class="mono">{{ $service->os ?? '—' }}</b></div>
                    <div class="instance-field"><span>规格</span><b class="mono">{{ $service->specs['cpu'] ?? 2 }} 核 / {{ $service->specs['memory_gb'] ?? 4 }} GB / @if ($isQzvps && empty($service->specs['disk_gb']))磁盘容量待主控同步 @else {{ $service->specs['disk_gb'] ?? 40 }} GB @endif</b></div>
                    <div class="instance-field"><span>带宽</span><b class="mono">{{ $uplinkBandwidth }} Mbps</b></div>
                    @if ($isQzvps)
                        <div class="instance-field"><span>CPU 型号</span><b>{{ data_get($service->specs, 'qzvps.cpu_model') ?: '主控未提供' }}</b></div>
                        <div class="instance-field"><span>线路 / 宿主节点</span><b>{{ data_get($service->specs, 'qzvps.line_name') ?: $service->region }} / {{ data_get($service->specs, 'qzvps.node_name') ?: data_get($service->specs, 'qzvps.node_id', '—') }}</b></div>
                        <div class="instance-field"><span>端口映射配额</span><b>{{ $isNatInstance ? $service->natMappings->count().' / '.data_get($service->specs, 'qzvps.port_num', 0).' 条' : '独立 IP，无需 NAT 映射' }}</b></div>
                        <div class="instance-field"><span>实际配置来源</span><b>轻舟实例实时同步</b></div>
                        <div class="instance-field"><span>系统盘</span><b>{{ data_get($service->specs, 'disk_gb') ? data_get($service->specs, 'disk_gb').' GB' : '主控暂未返回容量' }}</b></div>
                        <div class="instance-field"><span>数据盘</span><b>{{ (int) data_get($service->specs, 'data_disk_gb', 0) > 0 ? data_get($service->specs, 'data_disk_gb').' GB' : '未配置独立数据盘' }}</b></div>
                    @endif
                    <div class="instance-field"><span>到期时间</span><b class="mono">{{ $service->expires_at?->format('Y-m-d H:i') ?? '无限期' }}</b></div>
                    <div class="instance-field"><span>自动续费</span><b class="mono">{{ $service->auto_renew ? '已开启' : '未开启' }}</b></div>
                    <div class="instance-field"><span>最近任务</span><b><x-status :status="$service->provision_status" /></b></div>
                </div>

                <div class="instance-actions">
                    @foreach (['start' => '开机', 'stop' => '关机', 'reboot' => '重启'] as $action => $label)
                        <form method="POST" action="{{ route('console.services.action', $service) }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $action }}">
                            <button class="btn btn-ghost btn-sm" type="submit">{{ $label }}</button>
                        </form>
                    @endforeach
                    @if (($isUpstream || $isQzvps) && $upstreamReinstallOs !== [])
                        <button class="btn btn-danger btn-sm" type="button" data-reinstall-open>重装系统</button>
                        <dialog id="upstream-reinstall-dialog" class="reinstall-dialog">
                            <form method="POST" action="{{ route('console.services.action', $service) }}" data-reinstall-form>
                                @csrf
                                <input type="hidden" name="action" value="reinstall">
                                <div class="reinstall-dialog-head">
                                    <div>
                                        <h3>重装操作系统</h3>
                                        <p>选择要安装的系统版本，重装会清空系统盘数据。</p>
                                    </div>
                                    <button class="icon-btn" type="button" data-reinstall-close aria-label="关闭">×</button>
                                </div>
                                <div class="reinstall-dialog-body">
                                    <div class="reinstall-warning">重装期间服务器会短暂离线，请提前备份重要数据。</div>
                                    <div class="os-group-list">
                                        @foreach (collect($upstreamReinstallOs)->groupBy(fn ($os) => $os['group'] ?? '其他') as $group => $systems)
                                            @php $family = strtolower((string) $group); $logo = str_contains($family, 'ubuntu') ? 'U' : (str_contains($family, 'debian') ? 'D' : (str_contains($family, 'centos') ? 'C' : (str_contains($family, 'windows') ? 'W' : strtoupper(mb_substr($group, 0, 1))))); @endphp
                                            <section class="os-family">
                                                @php $familyClass = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $group)); @endphp
                                                <div class="os-family-head">
                                                    <span class="os-family-logo os-{{ trim($familyClass, '-') }}">{{ $logo }}</span>
                                                    <b>{{ $group }}</b>
                                                    <span class="os-family-count">{{ collect($systems)->count() }} 个版本</span>
                                                </div>
                                                <div class="os-version-grid">
                                                    @foreach ($systems as $os)
                                                        <label class="os-version-card">
                                                            <input type="radio" name="os" value="{{ $os['id'] ?? '' }}" @checked(($os['name'] ?? '') === $service->os) required>
                                                            <span class="os-version-radio" aria-hidden="true"></span>
                                                            <span class="os-version-name">{{ $os['name'] ?? '系统' }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="reinstall-dialog-foot">
                                    <button class="btn btn-ghost" type="button" data-reinstall-close>取消</button>
                                    <button class="btn btn-danger" type="submit">确认重装系统</button>
                                </div>
                            </form>
                        </dialog>

                        <dialog id="upstream-reinstall-confirm" class="reinstall-confirm-dialog">
                            <div class="reinstall-confirm-body">
                                <div class="reinstall-confirm-icon" aria-hidden="true">!</div>
                                <h3>确认重装系统？</h3>
                                <p>即将为实例重装为 <b data-reinstall-os-name>所选系统</b>，系统盘数据将被清空且无法恢复。</p>
                                <div class="reinstall-confirm-warning">重装期间服务器会短暂离线，请确认已完成数据备份。</div>
                                <div class="reinstall-confirm-actions">
                                    <button class="btn btn-ghost" type="button" data-reinstall-confirm-cancel>取消</button>
                                    <button class="btn btn-danger" type="button" data-reinstall-confirm-submit>确认重装</button>
                                </div>
                            </div>
                        </dialog>
                    @elseif (! $isUpstream)
                        <form method="POST" action="{{ route('console.services.action', $service) }}" data-confirm="重装系统会清空系统盘数据，确认继续？">@csrf<input type="hidden" name="action" value="reinstall"><button class="btn btn-danger btn-sm" type="submit">重装系统</button></form>
                    @else
                        <button class="btn btn-danger btn-sm" type="button" disabled title="暂未返回可用操作系统列表">重装系统</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="b-right-stack">
            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="network" :size="20" /></span><span class="dv-card-caption"><span>网络</span><small>公网访问与连接方式</small></span></h2><span class="meta">{{ $isUpstreamNat ? 'NAT 模式' : '独立 IP' }}</span></div>
                <div class="panel-body">
                    @php
                        $networkIps = $isUpstream ? array_values(array_filter((array) data_get($service->specs, 'network_ips', []))) : [];
                        if ($networkIps === [] && $service->ip_address) {
                            $networkIps = [$service->ip_address];
                        }
                    @endphp
                    @if ($isUpstream && ! $isUpstreamNat && $networkIps !== [])
                        <div class="flex justify-between" style="padding:7px 0;font-size:12px;align-items:flex-start">
                            <span class="muted">公网 IP</span>
                            <div style="display:grid;gap:6px;justify-items:end">
                                @foreach ($networkIps as $ip)
                                    <div class="flex items-center gap-2">
                                        <span class="chip">{{ str_contains($ip, ':') ? 'IPv6' : 'IPv4' }}</span>
                                        <b class="mono">{{ $ip }}</b>
                                        <button class="btn btn-ghost btn-sm" type="button" data-copy="{{ $ip }}">复制</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex justify-between" style="padding:7px 0;font-size:12px"><span class="muted">公网 IP</span><b class="mono">{{ $isUpstreamNat ? ($service->nat_ip ?: '待分配') : ($service->ip_address ?: $service->nat_ip ?: '待分配') }}</b></div>
                    @endif
                    @if ($isUpstreamNat)
                        <div class="flex justify-between" style="padding:7px 0;font-size:12px"><span class="muted">端口段</span><b class="mono">{{ $service->nat_port_start ?: '—' }} – {{ $service->nat_port_end ?: '—' }}</b></div>
                    @endif
                    @if ((! $isUpstream && ! $isQzvps) || $isUpstreamNat)
                        <div class="flex justify-between" style="padding:7px 0;font-size:12px"><span class="muted">内网地址</span><b class="mono">{{ data_get($service->specs, 'internal_ip') ?: $service->ip_address ?: $service->natMappings->first()?->internal_ip ?: '待分配' }}</b></div>
                    @endif
                </div>
            </div>

            @if ($isUpstream && $upstreamTraffic)
                <div class="panel">
                    <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="resource-monitor" :size="20" /></span><span class="dv-card-caption"><span>流量用量</span><small>查看资源配置与服务详情</small></span></h2><span class="meta">自动同步</span></div>
                    <div class="panel-body">
                        @php $trafficPercent = $upstreamTraffic['total'] > 0 && $upstreamTraffic['used'] !== null ? min(100, max(0, $upstreamTraffic['used'] / $upstreamTraffic['total'] * 100)) : 0; @endphp
                        <div class="flex justify-between" style="font-size:16px;font-weight:600;align-items:baseline"><span>已用流量</span><b style="font-size:22px">{{ $upstreamTraffic['used'] !== null ? number_format($upstreamTraffic['used'], 2).' GB' : '暂未提供' }}</b></div>
                        <div role="progressbar" aria-label="流量使用率" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($trafficPercent, 1, '.', '') }}" style="height:12px;background:#e5e7eb;border-radius:6px;overflow:hidden;margin:12px 0 8px"><div style="height:100%;width:{{ $trafficPercent }}%;background:#2563eb;border-radius:6px;transition:width .2s ease"></div></div>
                        <div class="flex justify-between muted small"><span>剩余 {{ $upstreamTraffic['remaining'] !== null ? number_format($upstreamTraffic['remaining'], 2).' GB' : '暂未提供' }}</span><span>{{ number_format($trafficPercent, 1) }}% 已使用 · 上限 {{ $upstreamTraffic['total'] !== null ? number_format($upstreamTraffic['total'], 2).' GB' : '不限' }}</span></div>
                        @if (is_array($upstreamTraffic['packets'] ?? null) && $upstreamTraffic['packets'] !== [])
                            <form class="mt-4 flex items-center gap-2 b-traffic-form" method="POST" action="{{ route('console.services.traffic', $service) }}">
                                @csrf
                                <select class="select" name="flow_packet_id" required><option value="">选择流量包</option>@foreach ($upstreamTraffic['packets'] as $packet)<option value="{{ $packet['id'] ?? '' }}">{{ $packet['name'] ?? '流量包' }} · ¥{{ number_format((float) ($packet['price'] ?? 0), 2) }}</option>@endforeach</select>
                                <button class="btn btn-primary btn-sm" type="submit">订购流量</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="resource-monitor" :size="20" /></span><span class="dv-card-caption"><span>资源监控</span><small>查看资源配置与服务详情</small></span></h2><span>独立页面</span></div>
                <div class="panel-body">
                    <p class="muted" style="margin:0 0 14px;font-size:12px;line-height:1.7">查看完整的 CPU、内存、磁盘和网卡监控，支持时间范围切换、悬停详情与滚轮缩放。</p>
                    <a class="btn btn-primary" href="{{ route('console.services.monitor', $service) }}">打开资源监控</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $remote = is_array(data_get($service->specs, 'remote')) ? data_get($service->specs, 'remote') : [];
        $remoteMapping = $service->natMappings->first();
        $remoteIsWindows = str_contains(strtolower((string) $service->os), 'windows');
        $remoteAddress = trim((string) ($remote['address'] ?? '')) ?: trim((string) ($remoteMapping?->external_ip ?: $service->nat_ip ?: $service->ip_address));
        $remotePort = $remote['port'] ?? null;
        $remotePort = $remotePort ?: ($remoteMapping?->external_port ?: ($remoteIsWindows ? 3389 : 22));
        $remoteUsername = trim((string) ($remote['username'] ?? '')) ?: ($remoteIsWindows ? 'Administrator' : 'root');
        $remotePassword = (string) ($remote['password'] ?? '');
        $remoteProtocol = strtoupper((string) ($remote['protocol'] ?? '')) ?: ($remoteIsWindows ? 'RDP' : 'SSH');
        $remoteEndpoint = \App\Support\RemoteEndpoint::forService($service);
        $remoteAddress = $remoteEndpoint['host'];
        $remotePort = $remoteEndpoint['port'];
        $remoteAddressText = $remoteEndpoint['display'] ?: '待分配';
    @endphp
    @include('partials.service-connection', ['service' => $service, 'connectionAdmin' => false])

    <div class="panel mt-4 b-renew-panel">
        <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="wallet" :size="20" /></span><span class="dv-card-caption"><span>续费</span><small>管理计费周期与服务续期</small></span></h2><span class="meta">提前续费从当前到期日顺延</span></div>
        <div class="panel-body">
            <div class="grid-2">
                <div>
                    @if ($renewalPrices->isEmpty())
                        <div class="note warn">该商品暂未配置可续费周期，请联系客服。</div>
                    @else
                        <form method="POST" action="{{ route('console.services.renew', $service) }}" class="filter-bar" style="align-items:flex-end">
                            @csrf
                            <div class="form-field" style="flex:1">
                                <label class="form-label">续费周期</label>
                                <select class="select" name="billing_cycle">
                                    @foreach ($renewalPrices as $price)
                                        <option value="{{ $price->billing_cycle }}" @selected(old('billing_cycle') === $price->billing_cycle)>
                                            {{ $cycles[$price->billing_cycle]['label'] ?? $price->billing_cycle }} · {{ \App\Support\Money::format($renewalQuotes[$price->billing_cycle] ?? $price->price) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary" type="submit">创建续费订单</button>
                        </form>
                    @endif
                </div>
                <div>
                    <form method="POST" action="{{ route('console.services.auto-renew', $service) }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $service->auto_renew ? 0 : 1 }}">
                        <button class="btn btn-soft" type="submit">{{ $service->auto_renew ? '关闭自动续费' : '开启自动续费' }}</button>
                    </form>
                    <div class="note mt-3">自动续费会在到期前 7 天生成续费订单；仍需账户余额充足并完成支付。</div>
                </div>
            </div>
        </div>
    </div>

    @if ($isNatInstance)
        <div class="panel mt-4 b-mapping-panel">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="port-forward" :size="20" /></span><span class="dv-card-caption"><span>端口映射</span><small>管理公网端口与内部服务</small></span></h2><span class="meta">外部 IP {{ $service->nat_ip ?: ($service->node?->host ?: '主控分配') }}</span></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('console.services.mappings.store', $service) }}" class="mapping-form">
                    @csrf
                    <div class="form-field mapping-field">
                        <div class="mapping-port-label">
                            <label class="form-label" for="external-port-input">外部端口</label>
                            <span class="mapping-help-wrap" id="external-port-help-wrap">
                                <button class="mapping-help-toggle" id="external-port-help-toggle" type="button" aria-label="外部端口说明" aria-expanded="false" aria-controls="external-port-help" aria-describedby="external-port-help">?</button>
                                <span class="mapping-help-content" id="external-port-help" role="tooltip" hidden>
                                    @if ($qzvpsPortRange)
                                        节点端口范围：<strong>{{ $qzvpsPortRange['min'] }}–{{ $qzvpsPortRange['max'] }}</strong>@if ($qzvpsPortRange['excluded'] !== [])，保留端口 {{ implode('、', $qzvpsPortRange['excluded']) }}@endif。
                                    @else
                                        外部端口用于公网访问。可用范围与端口占用以节点校验为准。
                                    @endif
                                </span>
                            </span>
                        </div>
                        <div class="mapping-port-input-wrap">
                            <input class="input" id="external-port-input" type="number" name="external_port"
                                min="{{ $qzvpsPortRange['min'] ?? 1 }}" max="{{ $qzvpsPortRange['max'] ?? 65535 }}"
                                @if ($qzvpsPortRange)
                                    data-port-min="{{ $qzvpsPortRange['min'] }}"
                                    data-port-max="{{ $qzvpsPortRange['max'] }}"
                                    data-port-excluded="{{ implode(',', $qzvpsPortRange['excluded']) }}"
                                    data-port-used="{{ implode(',', $qzvpsUsedPorts) }}"
                                    data-port-available="{{ implode(',', $qzvpsAvailablePorts) }}"
                                @endif
                                placeholder="{{ $qzvpsPortRange ? '输入外网端口' : '20000' }}" required>
                            <div id="external-port-suggestions" class="mapping-port-suggestions" hidden></div>
                        </div>
                    </div>
                    <div class="form-field mapping-field"><label class="form-label" for="mapping-internal-port">内部端口</label><input class="input" id="mapping-internal-port" type="number" name="internal_port" value="{{ old('internal_port') }}" min="1" max="65535" placeholder="例如 3389" required></div>
                    <div class="form-field mapping-field"><label class="form-label" for="mapping-protocol">协议</label><select class="select" id="mapping-protocol" name="protocol"><option value="tcp+udp" selected>TCP + UDP</option><option value="tcp">TCP</option><option value="udp">UDP</option></select></div>
                    <button class="btn btn-primary" type="submit"><x-icon name="plus" :size="14" /> 添加映射</button>
                </form>
                <div class="note info" style="margin-top:10px">内网地址由主控 IP 池自动分配，无需手动填写。</div>

                <div class="table-wrap mt-4">
                    <table class="table">
                        <thead><tr><th>外部地址</th><th>内部地址</th><th>协议</th><th>状态</th><th class="actions">操作</th></tr></thead>
                        <tbody>
                            @forelse ($service->natMappings as $mapping)
                                <tr>
                                    <td class="mono">{{ $mapping->external_ip }}:{{ $mapping->external_port }}</td>
                                    <td class="mono">{{ $mapping->internal_ip }}:{{ $mapping->internal_port }}</td>
                                    <td><span class="chip">{{ strtoupper($mapping->protocol) }}</span></td>
                                    <td><x-status :status="$mapping->status" /></td>
                                    <td class="actions">
                                        <form method="POST" action="{{ route('console.services.mappings.destroy', [$service, $mapping]) }}" data-confirm="确认删除该端口映射？">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty">还没有端口映射。添加一条后，节点会自动下发 nftables 规则。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="panel mt-4 b-task-panel">
        <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="activity" :size="20" /></span><span class="dv-card-caption"><span>最近任务</span><small>查看服务操作与执行记录</small></span></h2><span class="meta">{{ $isUpstream ? '由服务商处理' : '由主控下发到节点' }}</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>操作</th><th>状态</th><th>尝试次数</th><th>创建时间</th><th>完成时间</th></tr></thead>
                    <tbody>
                        @forelse ($service->provisionTasks as $task)
                            <tr>
                                <td class="strong">{{ $task->operation }}</td>
                                <td><x-status :status="$task->status" /></td>
                                <td>{{ $task->attempts }}</td>
                                <td class="small muted">{{ $task->created_at?->format('m-d H:i') }}</td>
                                <td class="small muted">{{ $task->completed_at?->format('m-d H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">暂无任务记录。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>

    <dialog id="ssh-remote-dialog" style="max-width:420px;width:calc(100% - 32px);padding:0;border:0;border-radius:16px;box-shadow:0 24px 80px rgba(15,23,42,.28)">
        <div style="padding:22px">
            <div class="flex items-center justify-between gap-3">
                <h3 style="margin:0;font-size:18px">使用 CShell 连接</h3>
                <form method="dialog" style="margin:0"><button class="icon-btn" type="submit" aria-label="关闭">×</button></form>
            </div>
            <p style="margin:16px 0 0;color:#475569;line-height:1.7">无法直接从您的设备调用SSH连接，推荐使用CShell连接</p>
            <div class="flex justify-end gap-2" style="margin-top:20px">
                <form method="dialog" style="margin:0"><button class="btn btn-ghost" type="submit">取消</button></form>
                <a class="btn btn-primary" href="https://shell.canp.ai/#download" target="_blank" rel="noopener">前往下载 CShell</a>
            </div>
        </div>
    </dialog>
</div>
@endsection

@push('styles')
<style>
.upstream-reinstall{position:relative}.upstream-reinstall>summary{list-style:none;cursor:pointer}.upstream-reinstall>summary::-webkit-details-marker{display:none}.upstream-reinstall[open]{width:100%;margin-top:8px}.upstream-reinstall form{margin-top:12px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc}.reinstall-title{font-weight:700;margin-bottom:12px}.os-group-list{display:grid;gap:12px}.os-family{padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:10px}.os-family-head{display:flex;align-items:center;gap:9px;margin-bottom:10px}.os-family-logo{display:inline-grid;place-items:center;width:32px;height:32px;border-radius:9px;background:#eef2ff;color:#3730a3;font-weight:800;font-size:15px}.os-ubuntu{background:#fff0eb;color:#e95420}.os-debian{background:#fff0f5;color:#a80030}.os-centos{background:#f4efff;color:#7b2cbf}.os-windows{background:#eaf6ff;color:#0078d4}.os-version-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:8px}.os-version-card{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #dbe3ee;border-radius:8px;cursor:pointer;font-size:13px}.os-version-card:has(input:checked){border-color:#2563eb;background:#eff6ff;color:#1d4ed8}.os-version-card input{accent-color:#2563eb}.reinstall-actions{display:flex;justify-content:flex-end;margin-top:14px}@media(max-width:720px){.os-version-grid{grid-template-columns:1fr}}
.reinstall-dialog{width:min(760px,calc(100% - 32px));max-height:calc(100vh - 48px);padding:0;border:0;border-radius:18px;box-shadow:0 28px 90px rgba(15,23,42,.35);overflow:hidden;background:#fff}
.reinstall-dialog::backdrop{background:rgba(15,23,42,.58);backdrop-filter:blur(3px)}
.reinstall-dialog form{display:flex;flex-direction:column;max-height:calc(100vh - 48px);margin:0;background:#fff}
.reinstall-dialog-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:22px 24px 16px;border-bottom:1px solid #e5e7eb}
.reinstall-dialog-head h3{margin:0;font-size:19px;color:#0f172a}
.reinstall-dialog-head p{margin:6px 0 0;color:#64748b;font-size:13px}
.reinstall-dialog-body{padding:18px 24px;overflow:auto}
.reinstall-warning{margin-bottom:16px;padding:11px 13px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb;color:#92400e;font-size:13px;line-height:1.6}
.reinstall-dialog .os-group-list{gap:14px}
.reinstall-dialog .os-family{padding:14px;background:#f8fafc;border-color:#e2e8f0;border-radius:12px}
.reinstall-dialog .os-family-head{gap:10px;margin-bottom:12px}
.reinstall-dialog .os-family-head b{font-size:14px;color:#1e293b}
.os-family-count{margin-left:auto;color:#94a3b8;font-size:12px;font-weight:500}
.reinstall-dialog .os-version-grid{grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px}
.reinstall-dialog .os-version-card{position:relative;padding:11px 12px;background:#fff;border-color:#dbe3ee;transition:border-color .15s ease,background .15s ease,box-shadow .15s ease}
.reinstall-dialog .os-version-card:hover{border-color:#93c5fd;box-shadow:0 2px 8px rgba(37,99,235,.08)}
.reinstall-dialog .os-version-card:has(input:checked){border-color:#2563eb;background:#eff6ff;box-shadow:0 0 0 1px #2563eb inset}
.reinstall-dialog .os-version-card input{position:absolute;opacity:0;width:1px;height:1px}
.os-version-radio{flex:0 0 16px;width:16px;height:16px;border:1.5px solid #cbd5e1;border-radius:50%;background:#fff;display:inline-grid;place-items:center}
.os-version-card input:checked + .os-version-radio{border-color:#2563eb}
.os-version-card input:checked + .os-version-radio::after{content:"";width:8px;height:8px;border-radius:50%;background:#2563eb}
.os-version-card input:focus-visible + .os-version-radio{outline:3px solid rgba(37,99,235,.25);outline-offset:2px}
.os-version-name{line-height:1.45;word-break:break-word}
.reinstall-dialog-foot{display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #e5e7eb;background:#fff}
@media(max-width:640px){.reinstall-dialog-head,.reinstall-dialog-body,.reinstall-dialog-foot{padding-left:16px;padding-right:16px}.reinstall-dialog .os-version-grid{grid-template-columns:1fr}}
.reinstall-confirm-dialog{width:min(430px,calc(100% - 32px));padding:0;border:0;border-radius:18px;box-shadow:0 28px 90px rgba(15,23,42,.35);overflow:hidden;background:#fff}
.reinstall-confirm-dialog::backdrop{background:rgba(15,23,42,.58);backdrop-filter:blur(3px)}
.reinstall-confirm-body{padding:26px 26px 22px;text-align:center}
.reinstall-confirm-icon{display:grid;place-items:center;width:52px;height:52px;margin:0 auto 14px;border-radius:50%;background:#fef2f2;color:#dc2626;font-size:26px;font-weight:800}
.reinstall-confirm-body h3{margin:0;font-size:19px;color:#0f172a}
.reinstall-confirm-body p{margin:10px 0 0;color:#64748b;font-size:13px;line-height:1.75}
.reinstall-confirm-body p b{color:#334155}
.reinstall-confirm-warning{margin-top:16px;padding:10px 12px;border:1px solid #fde68a;border-radius:9px;background:#fffbeb;color:#92400e;font-size:12px;line-height:1.6;text-align:left}
.reinstall-confirm-actions{display:flex;justify-content:center;gap:10px;margin-top:20px}
</style>
@endpush

@push('scripts')
<script nonce="{{ $cspNonce }}">
    (() => {
        const input = document.getElementById('external-port-input');
        const box = document.getElementById('external-port-suggestions');
        const helpToggle = document.getElementById('external-port-help-toggle');
        const help = document.getElementById('external-port-help');
        const helpWrap = document.getElementById('external-port-help-wrap');
        if (helpToggle && help && helpWrap) {
            document.body.append(help);
            let dismissTimer = 0;
            let pinned = false;
            let keyboardOpen = false;
            const positionHelp = () => {
                const anchor = helpToggle.getBoundingClientRect();
                const margin = 16;
                const width = help.offsetWidth;
                const left = Math.min(Math.max(margin, anchor.left), window.innerWidth - width - margin);
                const height = help.offsetHeight;
                const below = anchor.bottom + 8;
                const top = below + height <= window.innerHeight - margin ? below : Math.max(margin, anchor.top - height - 8);
                help.style.left = `${left}px`;
                help.style.top = `${top}px`;
            };
            const openHelp = () => {
                window.clearTimeout(dismissTimer);
                help.hidden = false;
                helpToggle.setAttribute('aria-expanded', 'true');
                positionHelp();
            };
            const closeHelp = () => {
                window.clearTimeout(dismissTimer);
                dismissTimer = window.setTimeout(() => {
                    if (pinned || keyboardOpen || helpWrap.matches(':hover') || help.matches(':hover')) return;
                    hideHelp();
                }, 180);
            };
            const hideHelp = () => {
                window.clearTimeout(dismissTimer);
                pinned = false;
                keyboardOpen = false;
                help.hidden = true;
                helpToggle.setAttribute('aria-expanded', 'false');
            };
            helpWrap.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') openHelp(); });
            helpWrap.addEventListener('pointerleave', event => { if (event.pointerType === 'mouse') closeHelp(); });
            help.addEventListener('pointerenter', () => window.clearTimeout(dismissTimer));
            help.addEventListener('pointerleave', closeHelp);
            helpToggle.addEventListener('focus', () => {
                if (!helpToggle.matches(':focus-visible')) return;
                keyboardOpen = true;
                openHelp();
            });
            helpToggle.addEventListener('blur', () => { keyboardOpen = false; closeHelp(); });
            helpToggle.addEventListener('click', () => {
                if (pinned) { hideHelp(); return; }
                if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) pinned = true;
                openHelp();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !help.hidden) hideHelp();
            });
            document.addEventListener('pointerdown', event => {
                if (pinned && !helpWrap.contains(event.target) && !help.contains(event.target)) hideHelp();
            });
            window.addEventListener('resize', () => { if (!help.hidden) positionHelp(); });
            window.addEventListener('scroll', () => { if (!help.hidden) positionHelp(); }, { passive: true });
        }
        if (!input || !box || !input.dataset.portMin) {
            return;
        }

        const min = Number(input.dataset.portMin);
        const max = Number(input.dataset.portMax);
        const excluded = new Set((input.dataset.portExcluded || '').split(',').filter(Boolean).map(Number));
        const used = new Set((input.dataset.portUsed || '').split(',').filter(Boolean).map(Number));
        const available = new Set((input.dataset.portAvailable || '').split(',').filter(Boolean).map(Number)
            .filter((port) => port >= min && port <= max && !excluded.has(port) && !used.has(port)));

        const hide = () => {
            box.hidden = true;
            box.innerHTML = '';
        };

        const showSuggestions = () => {
            const digits = input.value.replace(/\D/g, '');
            if (digits !== input.value) {
                input.value = digits;
            }

            input.setCustomValidity('');

            if (digits.length < 2 && available.size === 0) {
                hide();

                return;
            }

            const starts = [];
            const candidates = available.size ? Array.from(available) : null;
            const total = candidates ? candidates.length : (max - min + 1);
            for (let index = 0; index < total; index++) {
                const port = candidates ? candidates[index] : min + index;
                if (excluded.has(port) || used.has(port)) {
                    continue;
                }
                if (String(port).startsWith(digits)) {
                    starts.push(port);
                }
                if (starts.length >= 8) {
                    break;
                }
            }

            let matches = starts.slice(0, 8);
            if (!matches.length && available.size && digits === '') {
                matches = Array.from(available).slice(0, 8);
            }
            if (!matches.length) {
                hide();

                return;
            }

            box.innerHTML = '';
            matches.forEach((port) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.port = String(port);
                button.textContent = String(port);
                box.appendChild(button);
            });
            box.hidden = false;
        };

        input.addEventListener('input', showSuggestions);
        input.addEventListener('focus', showSuggestions);
        input.addEventListener('blur', () => {
            const port = Number(input.value);
            if (input.value !== '' && (port < min || port > max || excluded.has(port) || used.has(port))) {
                input.setCustomValidity('请输入 '.concat(min, '–', max, ' 范围内的可用端口'));
            } else {
                input.setCustomValidity('');
            }
            window.setTimeout(hide, 120);
        });

        box.addEventListener('click', (event) => {
            const button = event.target.closest('[data-port]');
            if (!button) {
                return;
            }
            input.value = button.dataset.port;
            input.setCustomValidity('');
            input.focus();
            hide();
        });

        document.addEventListener('click', (event) => {
            if (event.target !== input && !box.contains(event.target)) {
                hide();
            }
        });
    })();
</script>

<script nonce="{{ $cspNonce }}">
    (() => {
        const trigger = document.querySelector('[data-ssh-remote]');
        const dialog = document.getElementById('ssh-remote-dialog');
        if (!trigger || !dialog) return;
        trigger.addEventListener('click', () => dialog.showModal());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    })();

    (() => {
        const trigger = document.querySelector('[data-reinstall-open]');
        const dialog = document.getElementById('upstream-reinstall-dialog');
        const confirmDialog = document.getElementById('upstream-reinstall-confirm');
        if (!trigger || !dialog || !confirmDialog) return;

        const form = dialog.querySelector('[data-reinstall-form]');
        const confirmName = confirmDialog.querySelector('[data-reinstall-os-name]');
        let confirmed = false;

        trigger.addEventListener('click', () => dialog.showModal());
        dialog.querySelectorAll('[data-reinstall-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });

        form.addEventListener('submit', (event) => {
            if (confirmed) return;
            event.preventDefault();

            const selected = form.querySelector('input[name="os"]:checked');
            if (!selected) {
                form.querySelector('input[name="os"]')?.reportValidity();
                return;
            }

            const label = selected.closest('.os-version-card')?.querySelector('.os-version-name')?.textContent || '所选系统';
            if (confirmName) confirmName.textContent = label;
            confirmDialog.showModal();
        });

        confirmDialog.querySelector('[data-reinstall-confirm-cancel]')?.addEventListener('click', () => confirmDialog.close());
        confirmDialog.querySelector('[data-reinstall-confirm-submit]')?.addEventListener('click', () => {
            confirmed = true;
            confirmDialog.close();
            dialog.close();
            form.requestSubmit();
        });
        confirmDialog.addEventListener('click', (event) => {
            if (event.target === confirmDialog) confirmDialog.close();
        });
    })();
</script>
@endpush
