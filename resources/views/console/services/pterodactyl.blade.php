@extends('layouts.console')

@section('title', $service->name)
@section('page-title', '游戏服务器详情')

@section('content')
@include('partials.service-workspace-style')
<div class="dv-service-workspace" data-service-workspace>


    <div class="b-instance-hero">
        <div class="b-hero-top">
            <div class="b-hero-identity">
                <div class="b-hero-logo"><x-icon name="server" :size="24" /></div>
            <div>
                <h1>{{ $service->product?->name ?? $service->name }}</h1>
                <p>{{ $service->service_no }} · {{ $service->os ?: '游戏服务器' }} · 翼龙面板</p>
            </div>
        </div>
        </div>
        <div class="b-hero-actions">
            <a class="btn btn-hero" href="{{ route('console.services.index') }}">返回列表</a>
            @if ($panelServerId !== '')
                <a class="btn btn-hero-primary" href="{{ rtrim($panelBaseUrl, '/') }}/server/{{ $panelServerId }}" target="_blank" rel="noopener">打开游戏面板</a>
            @endif
            <a class="btn btn-hero" href="{{ route('console.tickets.create') }}">提交工单</a>
        </div>
    </div>

    <div class="grid-2" style="align-items:start">
        <div class="panel">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="users" :size="20" /></span><span class="dv-card-caption"><span>面板账号</span><small>登录与管理面板</small></span></h2><span class="meta">翼龙面板登录信息</span></div>
            <div class="panel-body">
                <div class="instance-info-grid">
                    <div class="instance-field"><span>面板地址</span><b class="mono">{{ $panelBaseUrl }}</b></div>
                    <div class="instance-field"><span>登录账号</span><b class="mono">{{ $panelEmail }}</b></div>
                    <div class="instance-field full remote-item">
                        <span>登录密码</span>
                        @if ($panelPassword !== '')
                            <div class="ptero-password-row">
                                <b class="mono remote-secret" data-secret-value="{{ $panelPassword }}">••••••••••••</b>
                                <button class="btn btn-ghost btn-sm" type="button" data-toggle-secret>显示</button>
                                <button class="btn btn-ghost btn-sm" type="button" data-copy="{{ $panelPassword }}">复制</button>
                            </div>
                        @else
                            <b class="muted">登录财务账号后自动同步</b>
                        @endif
                    </div>
                    <div class="instance-field"><span>服务器 ID</span><b class="mono">{{ $panelServerId ?: '开通中' }}</b></div>
                </div>
                <div class="note info" style="margin-top:14px">面板账号与财务登录账号一致，密码同步自当前财务账号。首次使用请先登录财务控制台，再打开游戏面板。</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="server" :size="20" /></span><span class="dv-card-caption"><span>服务器信息</span><small>资源配置与服务信息</small></span></h2><span class="meta">实例规格与计费</span></div>
            <div class="panel-body">
                <div class="instance-info-grid">
                    <div class="instance-field"><span>实例编号</span><b class="mono">{{ $service->service_no }}</b></div>
                    <div class="instance-field"><span>商品</span><b class="mono">{{ $service->product?->name }}</b></div>
                    <div class="instance-field"><span>规格</span><b class="mono">{{ $service->specs['cpu'] ?? 1 }} 核 / {{ $service->specs['memory_gb'] ?? 1 }} GB / {{ $service->specs['disk_gb'] ?? 5 }} GB</b></div>
                    <div class="instance-field"><span>到期时间</span><b class="mono">{{ $service->expires_at?->format('Y-m-d H:i') ?? '无限期' }}</b></div>
                    <div class="instance-field"><span>自动续费</span><b>{{ $service->auto_renew ? '已开启' : '未开启' }}</b></div>
                    <div class="instance-field"><span>所属节点</span><b class="mono">#{{ $panelNodeId ?: '—' }}</b></div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel mt-4 b-renew-panel">
        <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="wallet" :size="20" /></span><span class="dv-card-caption"><span>续费</span><small>管理计费周期与服务续期</small></span></h2><span class="meta">从当前到期日顺延</span></div>
        <div class="panel-body">
            <div class="grid-2">
                <div>
                    @if ($renewalPrices->isEmpty())
                        <div class="note warn">该商品暂未配置续费周期，请联系客服。</div>
                    @else
                        <form method="POST" action="{{ route('console.services.renew', $service) }}" class="filter-bar" style="align-items:flex-end">
                            @csrf
                            <div class="form-field" style="flex:1">
                                <label class="form-label">续费周期</label>
                                <select class="select" name="billing_cycle">
                                    @foreach ($renewalPrices as $price)
                                        <option value="{{ $price->billing_cycle }}">
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
                </div>
            </div>
        </div>
    </div>

    @if ($service->provisionTasks->isNotEmpty())
        <div class="panel mt-4">
            <div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="activity" :size="20" /></span><span class="dv-card-caption"><span>最近任务</span><small>查看服务操作与执行记录</small></span></h2><span class="meta">开通与操作记录</span></div>
            <div class="panel-body">
                @foreach ($service->provisionTasks as $task)
                    <div class="flex justify-between small" style="padding:7px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)">
                        <span>{{ $task->operation }}</span>
                        <x-status :status="$task->status" />
                        <span class="muted">{{ $task->created_at?->format('m-d H:i') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
