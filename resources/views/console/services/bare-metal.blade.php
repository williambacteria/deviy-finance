@extends($adminView ? 'layouts.admin' : 'layouts.console')
@section('title', $service->product?->name ?? $service->name)
@section('page-title', '裸金属服务器')
@section('content')
@include('partials.service-workspace-style')
<div class="dv-service-workspace" data-service-workspace data-product-family="EBM">
    @php
        $prefix = $adminView ? 'admin' : 'console';
        $permissions = (array) data_get($service->specs, 'upstream.capabilities', []);
        $bandwidthDisplay = \App\Support\ServicePresentation::bandwidth($service->product, (array) $service->specs);
        $configuration = (array) data_get($service->specs, 'upstream.configuration', []);
        $barePoints = array_values(array_filter((array) data_get($bareMetrics ?? [], 'charts.bandwidth.series.0.points', []), 'is_array'));
        $pointMaximum = max(array_merge([1], array_map(fn ($point) => (float) ($point['value'] ?? 0), $barePoints)));
        $polyline = implode(' ', array_map(fn ($point, $index) => round($index * 600 / max(1, count($barePoints) - 1), 2).','.round(145 - (float) ($point['value'] ?? 0) / $pointMaximum * 130, 2), $barePoints, array_keys($barePoints)));
        $flowUsed = data_get($upstreamDetail ?? [], 'data.host_data.bwusage');
        $flowLimit = data_get($upstreamDetail ?? [], 'data.host_data.bwlimit');
    @endphp
    <div class="b-instance-hero">
        <div class="dv-service-heading"><div class="b-hero-logo"><x-icon name="server" :size="26" /></div><div><h1>{{ $service->product?->name ?? $service->name }}</h1><p>{{ $service->service_no }} · 独享物理服务器 · {{ $service->region === 'default' ? '独立 IP' : $service->region }}</p></div><x-status :status="$service->status" /></div>
        <div class="b-hero-actions" style="margin-top:18px"><a class="btn btn-hero" href="{{ route($prefix.'.services.index') }}">返回服务器列表</a>@if (($permissions['novnc'] ?? 'off') === 'on')<a class="btn btn-hero-primary" href="{{ route($prefix.'.services.vnc', $service) }}" target="_blank" rel="noopener">打开远程控制台</a>@endif<a class="btn btn-hero" href="{{ route($prefix.'.tickets.index') }}">服务支持</a></div>
    </div>
    @if ($syncError)<div class="note warn mt-4">{{ str_replace(['魔方上游', '魔方', '上游'], '', (string) $syncError) }}</div>@endif
    <div class="dv-workspace-summary">
        <div class="dv-summary-item"><span>处理器</span><b>{{ data_get($service->specs, 'cpu', '—') }} <small>核心</small></b><small>{{ data_get($service->specs, 'cpu_model') ?: '独享计算资源' }}</small></div>
        <div class="dv-summary-item"><span>物理内存</span><b>{{ data_get($service->specs, 'memory_gb', '—') }} <small>GB</small></b><small>独享内存配置</small></div>
        <div class="dv-summary-item"><span>本地磁盘</span><b>{{ data_get($service->specs, 'disk_gb', '—') }} <small>GB</small></b><small>完整硬件配置见下方</small></div>
        <div class="dv-summary-item"><span>到期时间</span><b style="font-size:20px">{{ $service->expires_at?->format('Y-m-d') ?? '无限期' }}</b><small>{{ $service->expires_at?->format('H:i') }} · {{ \App\Support\Billing::label($service->billing_cycle) }}</small></div>
    </div>
    <div class="dv-bare-grid">
        @include('partials.service-connection', ['service' => $service, 'connectionAdmin' => $adminView, 'connectionResetAllowed' => ($permissions['crack_pass'] ?? 'off') === 'on'])
        <div class="panel"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="hardware-chip" :size="20" /></span><span class="dv-card-caption"><span>硬件与网络</span><small>硬件规格与公网配置</small></span></h2><span class="meta">当前实例配置</span></div><div class="panel-body"><table class="spec-table"><tbody>
            <tr><td>实例名称</td><td>{{ $service->name }}</td></tr><tr><td>操作系统</td><td>{{ $service->os ?: '待同步' }}</td></tr><tr><td>公网地址</td><td class="mono">{{ $service->ip_address ?: '待同步' }}</td></tr>
            <tr><td>上行带宽</td><td>{{ $bandwidthDisplay['upload'] !== null ? $bandwidthDisplay['upload'].' Mbps' : '待同步' }}</td></tr><tr><td>下行带宽</td><td>{{ $bandwidthDisplay['download'] !== null ? $bandwidthDisplay['download'].' Mbps' : '待同步' }}</td></tr><tr><td>带宽来源</td><td>{{ $bandwidthDisplay['source'] }}</td></tr>
            @foreach ($configuration as $item) @if ($item['label'] === '带宽') @continue @endif <tr><td>{{ $item['label'] }}</td><td>{{ $item['label'] === '带宽' && $item['value'] === '0' ? '依套餐配置分配' : $item['value'] }}</td></tr>@endforeach
            @if ($adminView)<tr><td>客户</td><td>{{ $service->user?->name }}</td></tr>@endif
        </tbody></table></div></div>
        <div class="panel"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="power" :size="20" /></span><span class="dv-card-caption"><span>电源与维护</span><small>电源控制与系统维护</small></span></h2><span class="meta">操作前请保存当前工作</span></div><div class="panel-body">
            <div class="dv-action-grid">@foreach (['start' => ['on', '开机', 'power'], 'stop' => ['off', '关机', 'stop'], 'reboot' => ['reboot', '重启', 'refresh']] as $operation => $definition) @if (($permissions[$definition[0]] ?? 'off') === 'on')<form method="POST" action="{{ route($prefix.'.services.action', $service) }}" data-confirm="确认{{ $definition[1] }}当前服务器？">@csrf<input type="hidden" name="action" value="{{ $operation }}"><button type="submit" class="dv-action-tile"><strong><x-icon :name="$definition[2]" :size="18" />{{ $definition[1] }}</strong><span>{{ $operation === 'start' ? '启动当前服务器' : '此操作将中断当前业务' }}</span></button></form>@endif @endforeach</div>
            <div class="dv-maintenance">
                @if (($permissions['reinstall'] ?? 'off') === 'on')<details><summary>重装操作系统</summary><form method="POST" action="{{ route($prefix.'.services.action', $service) }}" data-confirm="重装会清空系统盘数据。确认已备份并继续？">@csrf<input type="hidden" name="action" value="reinstall"><label class="form-label">系统镜像</label><select class="select" name="os" required><option value="">选择要安装的系统</option>@foreach ($reinstallOs as $image)<option value="{{ $image['id'] }}">{{ $image['group'] ?? '' }} {{ $image['name'] ?? '' }}</option>@endforeach</select><p class="small muted">重装会清空系统盘，请提前备份重要数据。</p><button class="btn btn-danger" type="submit">确认重装系统</button></form></details>@endif
                @if (($permissions['rescue'] ?? 'off') === 'on')<details><summary>启动救援系统</summary><form method="POST" action="{{ route($prefix.'.services.bare.maintenance', $service) }}" data-confirm="启动救援环境会中断当前业务，确认继续？">@csrf<input type="hidden" name="operation" value="rescue"><label class="form-label">救援环境</label><select class="select" name="system"><option value="1">Linux</option><option value="2">Windows</option></select><label class="form-label"><input type="checkbox" name="confirm_downtime" value="1" required> 已了解服务器将中断当前业务</label><button class="btn btn-primary" type="submit">启动救援环境</button></form></details>@endif
                @if (($permissions['bmc'] ?? 'off') === 'on')<details><summary>重置 BMC 管理控制器</summary><form method="POST" action="{{ route($prefix.'.services.bare.maintenance', $service) }}" data-confirm="确认重置 BMC 管理控制器？此操作可能影响远程管理连接。">@csrf<input type="hidden" name="operation" value="bmc"><p class="small muted">用于远程管理异常时重置管理控制器，请避免与其他维护操作同时执行。</p><label class="form-label"><input type="checkbox" name="confirm_downtime" value="1" required> 已了解并确认执行 BMC 重置</label><button class="btn btn-primary" type="submit">确认重置 BMC</button></form></details>@endif
            </div>
        </div></div>
        <div class="panel"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="resource-monitor" :size="20" /></span><span class="dv-card-caption"><span>流量与用量</span><small>查看流量计量与使用趋势</small></span></h2><span class="meta">实例计量记录</span></div><div class="panel-body"><p class="dv-traffic-total">{{ is_numeric($flowUsed) ? number_format((float) $flowUsed, 2).' GB' : '待同步' }}</p><p class="small muted">{{ is_numeric($flowLimit) && (float) $flowLimit > 0 ? '套餐流量 '.number_format((float) $flowLimit, 2).' GB' : '流量限制以当前套餐为准' }}</p>@if ($barePoints !== [])<svg class="dv-traffic-chart" viewBox="0 0 600 165" role="img" aria-label="流量使用趋势"><path d="M0 145H600M0 80H600M0 15H600" stroke="currentColor" stroke-opacity=".12" fill="none"/><polyline points="{{ $polyline }}" fill="none" stroke="currentColor" stroke-width="2.5" vector-effect="non-scaling-stroke"/></svg><div class="dv-traffic-caption"><span>{{ $barePoints[0]['time'] }}</span><span>{{ $barePoints[count($barePoints)-1]['time'] }}</span></div>@else<div class="note">当前暂无可展示的流量趋势，稍后刷新重试。</div>@endif</div></div>
        <div class="panel dv-bare-full"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="wallet" :size="20" /></span><span class="dv-card-caption"><span>计费与续费</span><small>服务账单与到期时间</small></span></h2><span class="meta">{{ $service->auto_renew ? '自动续费已开启' : '自动续费未开启' }}</span></div><div class="panel-body"><div class="grid-2"><div><label class="form-label">到期时间</label><b>{{ $service->expires_at?->format('Y-m-d H:i') ?: '无限期' }}</b></div><div>@if (! $adminView && $renewalPrices->isNotEmpty())<form method="POST" action="{{ route('console.services.renew', $service) }}" class="filter-bar">@csrf<select class="select" name="billing_cycle">@foreach ($renewalPrices as $price)<option value="{{ $price->billing_cycle }}">{{ \App\Support\Billing::label($price->billing_cycle) }} · {{ \App\Support\Money::format($renewalQuotes[$price->billing_cycle]) }}</option>@endforeach</select><button class="btn btn-primary" type="submit">创建续费订单</button></form>@else @foreach ($renewalPrices as $price)<p>{{ \App\Support\Billing::label($price->billing_cycle) }} · {{ \App\Support\Money::format($renewalQuotes[$price->billing_cycle]) }}</p>@endforeach @endif</div></div></div></div>
    </div>
</div>
@endsection
