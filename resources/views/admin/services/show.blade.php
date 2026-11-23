@extends('layouts.admin')

@section('title', '实例管理 · '.$service->name)
@section('page-title', '实例管理')

@section('content')
@include('partials.service-workspace-style')
<div class="dv-service-workspace" data-service-workspace>
    @php
        $isQzvps = $service->product?->upstream_source === 'qzvps';
        $isMofang = $service->product?->upstream_source === 'mofang';
        $isPterodactyl = $service->product?->upstream_source === 'pterodactyl';
        $pteroServerId = $isPterodactyl ? preg_replace('/^pterodactyl:/', '', (string) $service->external_ref) : '';
        $remote = is_array(data_get($service->specs, 'remote')) ? data_get($service->specs, 'remote') : [];
        $remoteIsWindows = str_contains(strtolower((string) $service->os), 'windows');
        $remoteAddress = trim((string) ($remote['address'] ?? '')) ?: trim((string) ($service->nat_ip ?: $service->ip_address));
        $remoteEndpoint = \App\Support\RemoteEndpoint::forService($service);
        $remoteAddress = $remoteEndpoint['host'];
        $remotePort = $remoteEndpoint['port'];
        $remoteUser = trim((string) ($remote['username'] ?? '')) ?: ($remoteIsWindows ? 'Administrator' : 'root');
        $networkIps = array_values(array_filter((array) data_get($service->specs, 'network_ips', [])));
        if ($networkIps === [] && $service->ip_address) {
            $networkIps = [$service->ip_address];
        }
    @endphp

    <div class="b-instance-hero" style="margin-bottom:18px">
        <div class="b-hero-top">
            <div class="b-hero-identity">
                <div class="b-hero-logo"><x-icon name="server" :size="24" /></div>
                <div>
                    <h1>{{ $service->name }}</h1>
                    <p>{{ $service->service_no }} · {{ $service->user?->name }} · {{ $service->product?->name }} · {{ $isMofang ? '实例' : ($isPterodactyl ? '翼龙面板实例' : '主控实例') }}</p>
                </div>
            </div>
            <x-status :status="$service->status" class="b-hero-status" />
        </div>
        <div class="b-hero-actions">
            <a class="btn btn-hero" href="{{ route('admin.services.index') }}">返回列表</a>
            <a class="btn btn-hero" href="{{ route('admin.customers.show', $service->user) }}">客户详情</a>
            @if (! $isPterodactyl)
                <a class="btn btn-hero-primary" href="{{ route('admin.services.vnc', $service) }}" target="_blank" rel="noopener">VNC 控制台</a>
            @endif
            @if ($isPterodactyl && $pteroServerId !== '')
                <a class="btn btn-hero" href="{{ rtrim($pterodactylBaseUrl, '/') }}/server/{{ $pteroServerId }}" target="_blank" rel="noopener">打开翼龙面板</a>
            @endif
            <form method="POST" action="{{ route('admin.customers.impersonate', $service->user) }}" style="margin:0">
                @csrf
                <input type="hidden" name="redirect" value="{{ route('console.services.show', $service) }}">
                <button class="btn btn-hero" type="submit">以客户身份查看</button>
            </form>
            <form method="POST" action="{{ route('admin.customers.impersonate', $service->user) }}" style="margin:0">
                @csrf
                <input type="hidden" name="redirect" value="{{ route('console.services.monitor', $service) }}">
                <button class="btn btn-hero" type="submit">资源监控</button>
            </form>
        </div>
    </div>

    <div class="panel" style="margin-bottom:16px">
        <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="server" :size="20" /></span><span class="dv-card-caption"><span>实例操作</span><small>查看资源配置与服务详情</small></span></h2><span class="meta">管理员操作会记录审计日志</span></div>
        <div class="panel-body">
            <div class="flex gap-2" style="flex-wrap:wrap;align-items:center">
                @foreach (['start' => '开机', 'stop' => '关机', 'reboot' => '重启'] as $action => $label)
                    <form method="POST" action="{{ route('admin.services.action', $service) }}" style="margin:0">
                        @csrf
                        <input type="hidden" name="action" value="{{ $action }}">
                        <button class="btn btn-ghost btn-sm" type="submit">{{ $label }}</button>
                    </form>
                @endforeach

                @if ($reinstallOs !== [])
                    <form method="POST" action="{{ route('admin.services.action', $service) }}" class="filter-bar" style="margin:0;align-items:center" data-confirm="重装系统会清空系统盘数据，确认继续？">
                        @csrf
                        <input type="hidden" name="action" value="reinstall">
                        <select class="select" name="os" required style="min-width:220px">
                            <option value="">选择要重装的操作系统</option>
                            @foreach (collect($reinstallOs)->groupBy(fn ($os) => $os['group'] ?? '其他') as $group => $systems)
                                <optgroup label="{{ $group }}">
                                    @foreach ($systems as $os)
                                        <option value="{{ $os['id'] ?? '' }}">{{ $os['name'] ?? '系统' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <button class="btn btn-danger btn-sm" type="submit">重装系统</button>
                    </form>
                @endif

                @if ($isPterodactyl)
                    <form method="POST" action="{{ route('admin.services.action', $service) }}" style="margin:0" data-confirm="重装会清空游戏服务器数据，确认继续？">
                        @csrf
                        <input type="hidden" name="action" value="reinstall">
                        <button class="btn btn-danger btn-sm" type="submit">重装服务器</button>
                    </form>
                @endif

                <span style="width:1px;height:22px;background:var(--dt-b-e7edf6,#e7edf6);margin:0 4px"></span>
                @foreach (['suspend' => '暂停', 'unsuspend' => '恢复'] as $action => $label)
                    <form method="POST" action="{{ route('admin.services.action', $service) }}" style="margin:0">
                        @csrf
                        <input type="hidden" name="action" value="{{ $action }}">
                        <button class="btn btn-ghost btn-sm" type="submit">{{ $label }}</button>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('admin.services.action', $service) }}" style="margin:0" data-confirm="确认终止该实例？此操作不可撤销。">
                    @csrf
                    <input type="hidden" name="action" value="terminate">
                    <button class="btn btn-danger btn-sm" type="submit">终止实例</button>
                </form>
            </div>
            <div class="note info" style="margin-top:10px">“以客户身份查看”和“资源监控”会进入该客户的实例视角；管理员操作不会创建客户账单。</div>
        </div>
    </div>

    <div class="grid-2">
        <div style="display:grid;gap:16px">
            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="server" :size="20" /></span><span class="dv-card-caption"><span>实例信息</span><small>查看当前实例与资源配置</small></span></h2><span class="meta">客户：{{ $service->user?->name }}</span></div>
                <div class="panel-body">
                    <table class="spec-table">
                        <tr><td>客户</td><td><a href="{{ route('admin.customers.show', $service->user) }}" style="color:var(--dt-t-2563eb,#2563eb)">{{ $service->user?->name }}（{{ $service->user?->email }}）</a></td></tr>
                        <tr><td>商品</td><td>{{ $service->product?->name ?? '—' }} <span class="chip">{{ $isMofang ? '云服务器' : ($isPterodactyl ? '翼龙面板' : '主控') }}</span></td></tr>
                        <tr><td>实例编号</td><td class="mono">{{ $service->service_no }}</td></tr>
                        <tr><td>主控节点</td><td class="mono">{{ $isQzvps ? (data_get($service->specs, 'qzvps.node_name') ?: data_get($service->specs, 'qzvps.node_id', '—')) : (data_get($service->product?->metadata, 'controller.node_id') ?: ($service->node?->agent_node_id ?: '—')) }}</td></tr>
                        <tr><td>实例引用</td><td class="mono">{{ $service->external_ref ?: '尚未创建' }}</td></tr>
                        @if ($isPterodactyl)
                            <tr><td>面板服务器</td><td class="mono">@if ($pteroServerId !== '')<a href="{{ rtrim($pterodactylBaseUrl, '/') }}/server/{{ $pteroServerId }}" target="_blank" rel="noopener" style="color:var(--dt-t-2563eb,#2563eb)">#{{ $pteroServerId }} · 打开面板</a>@else 待创建 @endif</td></tr>
                        @endif
                        <tr><td>操作系统</td><td>{{ $service->os ?? '—' }}</td></tr>
                        <tr><td>规格</td><td>{{ $service->specs['cpu'] ?? 2 }}C / {{ $service->specs['memory_gb'] ?? 4 }}G / @if ($isQzvps)系统盘 {{ data_get($service->specs, 'disk_gb') ? data_get($service->specs, 'disk_gb').'G' : '待同步' }} / 数据盘 {{ data_get($service->specs, 'data_disk_gb', 0) }}G @else {{ $service->specs['disk_gb'] ?? 40 }}G @endif</td></tr>
                        <tr><td>带宽</td><td>{{ $service->bandwidth_mbps }} Mbps</td></tr>
                        <tr><td>到期时间</td><td>{{ $service->expires_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
                        <tr><td>下次到期</td><td>{{ $service->next_due_at?->format('Y-m-d') ?? '—' }}</td></tr>
                        <tr><td>自动续费</td><td>{{ $service->auto_renew ? '已开启' : '未开启' }}</td></tr>
                        <tr><td>开通状态</td><td><x-status :status="$service->provision_status" /></td></tr>
                        @if ($localSample)
                            <tr><td>实时指标</td><td class="small">CPU {{ number_format($localSample->cpu_percent, 1) }}% · 内存 {{ number_format($localSample->memory_used_gb, 2) }} GB · 磁盘 {{ number_format($localSample->disk_read_mbps, 2) }}/{{ number_format($localSample->disk_write_mbps, 2) }} MB/s</td></tr>
                        @endif
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="network" :size="20" /></span><span class="dv-card-caption"><span>网络</span><small>公网访问与连接方式</small></span></h2><span class="meta">{{ $service->nat_ip ? 'NAT 模式' : '独立 IP' }}</span></div>
                <div class="panel-body" style="display:grid;gap:9px">
                    @forelse ($networkIps as $ip)
                        <div class="flex justify-between" style="align-items:center;gap:10px">
                            <span class="muted small">{{ str_contains($ip, ':') ? 'IPv6' : 'IPv4' }}</span>
                            <b class="mono">{{ $ip }}</b>
                            <button class="btn btn-ghost btn-sm" type="button" data-copy="{{ $ip }}">复制</button>
                        </div>
                    @empty
                        <div class="small muted">暂无 IP 信息。</div>
                    @endforelse
                    @if ($service->nat_ip)
                        <div class="flex justify-between" style="border-top:1px solid var(--dt-e-edf2f9,#edf2f9);padding-top:9px"><span class="muted small">NAT</span><b class="mono">{{ $service->nat_ip }}:{{ $service->nat_port_start }}–{{ $service->nat_port_end }}</b></div>
                    @endif
                    @foreach ($service->natMappings as $mapping)
                        <div class="flex justify-between small" style="border-top:1px solid var(--dt-e-f1f5f9,#f1f5f9);padding-top:8px">
                            <span class="mono">{{ $mapping->external_ip }}:{{ $mapping->external_port }}</span>
                            <span class="mono muted">→ {{ $mapping->internal_ip }}:{{ $mapping->internal_port }}</span>
                            <x-status :status="$mapping->status" />
                        </div>
                    @endforeach
                </div>
            </div>

            @include('partials.service-connection', ['service' => $service, 'connectionAdmin' => true])
        </div>

        <div style="display:grid;gap:16px">
            @if ($isMofang)
                <div class="panel">
                    <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="server" :size="20" /></span><span class="dv-card-caption"><span>实例记录</span><small>服务关联与计量记录</small></span></h2></div>
                    <div class="panel-body">
                        <table class="spec-table">
                            <tr><td>实例 ID</td><td class="mono">{{ $service->upstreamOrder?->upstream_service_id ?: '—' }}</td></tr>
                            <tr><td>订单 ID</td><td class="mono">{{ $service->upstreamOrder?->upstream_order_id ?: '—' }}</td></tr>
                            <tr><td>流量用量</td><td>{{ $upstreamTraffic && $upstreamTraffic['used'] !== null ? number_format((float) $upstreamTraffic['used'], 2).' GB' : '—' }} @if ($upstreamTraffic && $upstreamTraffic['total'])<span class="muted small">/ {{ number_format((float) $upstreamTraffic['total'], 2) }} GB</span>@endif</td></tr>
                        </table>
                        @if ($upstreamTraffic && $upstreamTraffic['packets'] !== [])
                            <form method="POST" action="{{ route('admin.services.traffic', $service) }}" class="filter-bar" style="margin-top:14px">
                                @csrf
                                <div class="form-field" style="flex:1"><label class="form-label">流量包</label><select class="select" name="flow_packet_id" required><option value="">选择流量包</option>@foreach ($upstreamTraffic['packets'] as $packet)<option value="{{ $packet['id'] ?? '' }}">{{ $packet['name'] ?? '流量包' }} · ¥{{ number_format((float) ($packet['price'] ?? 0), 2) }}</option>@endforeach</select></div>
                                <button class="btn btn-primary btn-sm" type="submit">订购流量</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="wallet" :size="20" /></span><span class="dv-card-caption"><span>计费管理</span><small>到期时间与续费管理</small></span></h2><span class="meta">到期与续费</span></div>
                <div class="panel-body">
                    <div class="flex justify-between" style="align-items:center;padding:4px 0 12px"><span class="muted small">自动续费</span><form method="POST" action="{{ route('admin.services.auto-renew', $service) }}" style="margin:0">@csrf<button class="btn btn-ghost btn-sm" type="submit">{{ $service->auto_renew ? '关闭自动续费' : '开启自动续费' }}</button></form></div>
                    <form method="POST" action="{{ route('admin.services.extend', $service) }}" class="filter-bar" style="align-items:flex-end">
                        @csrf
                        <div class="form-field" style="flex:1"><label class="form-label">调整到期时间</label><input class="input" type="datetime-local" name="expires_at" value="{{ old('expires_at', $service->expires_at?->format('Y-m-d\TH:i')) }}" required></div>
                        <button class="btn btn-primary btn-sm" type="submit">保存到期</button>
                    </form>
                    <div style="margin-top:14px;border-top:1px solid var(--dt-e-edf2f9,#edf2f9);padding-top:12px">
                        <div class="muted small" style="margin-bottom:8px">续费报价</div>
                        @forelse ($renewalPrices as $price)
                            <div class="flex justify-between small" style="padding:5px 0"><span>{{ $cycles[$price->billing_cycle]['label'] ?? $price->billing_cycle }}</span><b>{{ \App\Support\Money::format($renewalQuotes[$price->billing_cycle] ?? $price->price) }}</b></div>
                        @empty
                            <div class="small muted">暂未配置续费周期。</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="activity" :size="20" /></span><span class="dv-card-caption"><span>最近任务</span><small>查看服务操作与执行记录</small></span></h2><span class="meta">主控 / 处理记录</span></div>
                <div class="panel-body">
                    @forelse ($service->provisionTasks as $task)
                        <div class="flex justify-between small" style="padding:7px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)"><span>{{ $task->operation }}</span><x-status :status="$task->status" /><span class="muted">{{ $task->created_at?->format('m-d H:i') }}</span></div>
                    @empty
                        <div class="small muted">暂无任务记录。</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
