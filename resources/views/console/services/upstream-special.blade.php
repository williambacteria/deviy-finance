@extends($adminView ? 'layouts.admin' : 'layouts.console')

@section('title', $service->product?->name ?? $service->name)
@section('page-title', $isCdn ? 'CDN 服务详情' : '裸金属服务详情')

@section('content')
@include('partials.service-workspace-style')
<div class="dv-service-workspace" data-service-workspace>
    @php
        $endpoint = \App\Support\RemoteEndpoint::forService($service);
        $remote = (array) data_get($service->specs, 'remote', []);
        $cdn = $cdnData ?? ['fields' => [], 'usage' => [], 'login_url' => null, 'errors' => []];
        $permissions = (array) data_get($service->specs, 'upstream.capabilities', []);
        $configuration = (array) data_get($service->specs, 'upstream.configuration', []);
        $prefix = $adminView ? 'admin' : 'console';
        $panelAccount = $cdn['fields']['面板账号'] ?? data_get($service->specs, 'cdn.account', '');
        $panelPassword = $cdn['fields']['面板密码'] ?? data_get($service->specs, 'cdn.password', '');
    @endphp
    <style>
        .mf-special-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px; }
        .mf-special-grid .panel { min-width:0; }
        .mf-facts { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px; }
        .mf-fact { display:grid;gap:7px;min-width:0;overflow-wrap:anywhere; }
        .mf-fact > span { color:var(--dt-t-64748b,#64748b);font-size:14px; }
        .mf-fact > b { font-size:16px;line-height:1.6; }
        .mf-ops { display:flex;flex-wrap:wrap;gap:10px;align-items:center; }
        .mf-special-grid .spec-table { width:100%; }
        .mf-special-grid .spec-table td { overflow-wrap:anywhere; }
        @media(max-width:760px) { .mf-special-grid { grid-template-columns:minmax(0,1fr); }.mf-facts { grid-template-columns:minmax(0,1fr); } }
    </style>
    <div class="page-head">
        <div><h1>{{ $service->product?->name ?? $service->name }}</h1><p>{{ $service->service_no }} · {{ $isCdn ? 'CDN 加速服务' : '独享裸金属服务器' }} <x-status :status="$service->status" /></p></div>
        <a class="btn btn-ghost" href="{{ route($prefix.'.services.index') }}">返回产品列表</a>
    </div>
    @if ($adminView)
        <div class="note info" style="margin-bottom:16px">客户：{{ $service->user?->name }} · {{ $service->user?->email }} · 实例 {{ data_get($service->specs, 'upstream.service_id') }}</div>
    @endif

    @if ($syncError)
        <div class="note warn" style="margin-bottom:16px">{{ str_replace(['', '', ''], '', (string) $syncError) }}</div>
    @endif
    <div class="mf-special-grid">
        <div class="panel">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="server" :size="20" /></span><span class="dv-card-caption"><span>{{ $isCdn ? '套餐配置' : '硬件配置' }}</span><small>查看资源配置与服务详情</small></span></h2><span class="meta">以实例选配记录为准</span></div>
            <div class="panel-body">
                <table class="spec-table"><tbody>
                    @forelse ($configuration as $item)
                        <tr><td>{{ $item['label'] }}</td><td>{{ $item['value'] }}</td></tr>
                    @empty
                        <tr><td>配置</td><td>暂未获取，请稍后刷新或联系客服。</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>
        @if ($isCdn)
        <div class="panel">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="globe" :size="20" /></span><span class="dv-card-caption"><span>CDN 控制台</span><small>域名接入与加速管理</small></span></h2></div>
            <div class="panel-body">
                <div class="mf-facts">
                    @if ($isCdn)
                        <div class="mf-fact"><span>面板账号</span><b class="mono">{{ $panelAccount ?: '暂未获取' }}</b></div>
                        <div class="mf-fact" data-cdn-credentials><span>面板密码</span><div style="display:grid;gap:10px"><input class="input mono" type="password" readonly value="{{ $panelPassword }}" aria-label="CDN 面板密码" autocomplete="off" data-cdn-password><div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn btn-ghost btn-sm" type="button" data-cdn-reveal aria-pressed="false">显示密码</button><button class="btn btn-ghost btn-sm" type="button" data-cdn-copy>复制密码</button><span class="small muted" role="status" data-cdn-copy-feedback></span></div></div></div>
                    @endif
                </div>
                @if ($isCdn && ! empty($cdn['login_url']))
                    <a class="btn btn-primary" style="margin-top:18px" href="{{ $cdn['login_url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">一键登录 CDN 面板</a>
                    <p class="small muted" style="margin-top:10px">域名接入、证书、缓存及访问控制请在 CDN 面板管理。</p>
                @elseif ($isCdn)
                    <div class="note warn" style="margin-top:16px">CDN 面板入口暂时未返回，请稍后刷新或联系客服。</div>
                @endif
            </div>
        </div>
        @else
            @include('partials.service-connection', ['service' => $service, 'connectionAdmin' => $adminView, 'connectionResetAllowed' => ($permissions['crack_pass'] ?? 'off') === 'on'])
        @endif
        @if ($isCdn)
            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="activity" :size="20" /></span><span class="dv-card-caption"><span>套餐用量</span><small>查看套餐资源与使用情况</small></span></h2><span class="meta">实时读取 CDN 面板</span></div>
                <div class="panel-body mf-facts">
                    @forelse ($cdn['usage'] as $label => $value)
                        <div class="mf-fact"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                    @empty
                        <div class="note warn">套餐用量暂未返回，请在 CDN 面板查看。</div>
                    @endforelse
                </div>
            </div>
            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="package" :size="20" /></span><span class="dv-card-caption"><span>升级包 / 流量包</span><small>套餐容量与附加资源</small></span></h2><span class="meta">同步套餐</span></div>
                <div class="panel-body mf-facts">
                    <div class="mf-fact"><span>已购升级包</span><b>{{ $cdn['usage']['已购升级包'] ?? '暂未返回' }}</b></div>
                    <div class="mf-fact"><span>已购流量包</span><b>{{ $cdn['usage']['已购流量包'] ?? '暂未返回' }}</b></div>
                    <div class="note info" style="grid-column:1/-1">{{ data_get($service->specs, 'upstream.allow_upgrade_config') ? '当前升级配置页未提供可调整选项，暂不可调整。' : '未开放此套餐的配置升级，当前不可订购额外升级包。' }}</div>
                </div>
            </div>
            <div class="panel" style="grid-column:1/-1">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="shield" :size="20" /></span><span class="dv-card-caption"><span>机房白名单</span><small>域名备案与机房接入</small></span></h2><span class="meta">按机房规则提交</span></div>
                <div class="panel-body">
                    <p class="muted">请提交已完成 ICP 备案的域名，处理时间与结果以机房返回为准。</p>
                    <form method="POST" action="{{ route($prefix.'.services.cdn.whitelist', $service) }}" style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">
                        @csrf<input class="input" name="domain" type="text" maxlength="253" required placeholder="www.example.com 或 example.com" style="flex:1 1 240px"><button class="btn btn-primary" type="submit">提交白名单</button>
                    </form>
                    <div class="table-wrap"><table class="table"><thead><tr><th>域名</th><th>机房</th><th>结果</th><th>说明</th><th>提交时间</th></tr></thead><tbody>
                        @forelse ($cdn['whitelist'] ?? [] as $record)
                            <tr>@foreach ($record as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">{{ in_array('whitelist', $cdn['errors'], true) ? '白名单记录暂时不可用，请稍后刷新。' : '暂无提交记录' }}</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>
            </div>
        @else
            <div class="panel">
                <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="settings" :size="20" /></span><span class="dv-card-caption"><span>服务器操作</span><small>管理电源与操作系统</small></span></h2><span class="meta">仅展示允许的操作</span></div>
                <div class="panel-body">
                    <div class="mf-ops">
                        @foreach (['start' => ['on', '开机'], 'stop' => ['off', '关机'], 'reboot' => ['reboot', '重启']] as $action => $definition)
                            @if (($permissions[$definition[0]] ?? 'off') === 'on')
                                <form method="POST" action="{{ route($prefix.'.services.action', $service) }}" data-confirm="确认执行{{ $definition[1] }}？">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="btn btn-ghost" type="submit">{{ $definition[1] }}</button></form>
                            @endif
                        @endforeach
                        @if (($permissions['novnc'] ?? 'off') === 'on')
                            <a class="btn btn-ghost" href="{{ route($prefix.'.services.vnc', $service) }}" target="_blank" rel="noopener">远程控制台</a>
                        @endif
                    </div>
                    @if (($permissions['reinstall'] ?? 'off') === 'on' && $reinstallOs !== [])
                        <form method="POST" action="{{ route($prefix.'.services.action', $service) }}" class="mt-4" data-confirm="重装会清空系统盘数据，确认继续？">
                            @csrf<input type="hidden" name="action" value="reinstall"><label class="form-label">重装系统</label><select class="select" name="os" required><option value="">选择操作系统</option>@foreach ($reinstallOs as $image)<option value="{{ $image['id'] }}">{{ $image['group'] ?? '' }} {{ $image['name'] ?? '' }}</option>@endforeach</select><button class="btn btn-danger mt-3" type="submit">确认重装</button>
                        </form>
                    @endif

                </div>
            </div>
        @endif
        <div class="panel">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="wallet" :size="20" /></span><span class="dv-card-caption"><span>续费与到期</span><small>管理服务周期与续费</small></span></h2></div>
            <div class="panel-body">
                <div class="mf-fact"><span>到期时间</span><b>{{ $service->expires_at?->format('Y-m-d H:i') ?: '—' }}</b></div>
                @if (! $adminView && $renewalPrices->isNotEmpty())
                    <form method="POST" action="{{ route('console.services.renew', $service) }}" class="mt-4">@csrf<label class="form-label">续费周期</label><select class="select" name="billing_cycle">@foreach ($renewalPrices as $price)<option value="{{ $price->billing_cycle }}">{{ \App\Support\Billing::label($price->billing_cycle) }} · {{ \App\Support\Money::format($renewalQuotes[$price->billing_cycle]) }}</option>@endforeach</select><button class="btn btn-primary mt-3" type="submit">创建续费订单</button></form>
                @elseif ($adminView)
                    @foreach ($renewalPrices as $price)<p>{{ \App\Support\Billing::label($price->billing_cycle) }} · {{ \App\Support\Money::format($renewalQuotes[$price->billing_cycle]) }}</p>@endforeach
                @endif
            </div>
        </div>
    </div>
@pushOnce('scripts', 'cdn-credential-controls')
    <script src="{{ asset('assets/js/cdn-credentials.js') }}?v={{ filemtime(public_path('assets/js/cdn-credentials.js')) }}"></script>
@endPushOnce
</div>
@endsection
