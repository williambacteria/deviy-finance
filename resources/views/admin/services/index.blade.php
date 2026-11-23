@extends('layouts.admin')

@section('title', '业务管理')
@section('page-title', '业务管理')

@section('content')
    <div class="page-head">
        <div><h1>业务管理</h1><p>统一管理魔方上游实例与主控实例，查看客户、规格、网络、状态和操作入口。</p></div>
        <div class="page-actions">
            <form method="GET" class="filter-bar">
                <input class="input" type="search" name="q" value="{{ $search }}" placeholder="实例名 / 编号 / IP">
                <select class="select" name="source">
                    <option value="">全部来源</option>
                    <option value="mofang" @selected($source === 'mofang')>魔方上游</option>
                    <option value="pterodactyl" @selected($source === 'pterodactyl')>翼龙面板</option>
                    <option value="local" @selected($source === 'local')>主控</option>
                </select>
                <select class="select" name="status">
                    <option value="">全部状态</option>
                    @foreach (['active' => '运行中', 'provisioning' => '开通中', 'pending' => '待开通', 'suspended' => '已暂停', 'terminated' => '已终止'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="select" name="provider">
                    <option value="">全部节点</option>
                    <option value="kvm" @selected($provider === 'kvm')>KVM</option>
                    <option value="pve" @selected($provider === 'pve')>PVE</option>
                    <option value="hyperv" @selected($provider === 'hyperv')>Hyper-V</option>
                </select>
                <button class="btn btn-ghost" type="submit">筛选</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>实例</th><th>客户</th><th>商品 / 来源</th><th>节点 / 主控</th><th>IP / 端口</th><th>状态</th><th>到期</th><th class="actions">操作</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($services as $service)
                            @php
                                $isMofang = $service->product?->upstream_source === 'mofang';
                                $isPterodactyl = $service->product?->upstream_source === 'pterodactyl';
                                $controllerId = data_get($service->product?->metadata, 'controller.node_id') ?: $service->node?->agent_node_id;
                            @endphp
                            <tr>
                                <td>
                                    <a class="strong" href="{{ route('admin.services.show', $service) }}">{{ $service->name }}</a>
                                    <div class="sub mono">{{ $service->service_no }}</div>
                                    <div class="sub">{{ $service->specs['cpu'] ?? 2 }}C / {{ $service->specs['memory_gb'] ?? 4 }}G / {{ $service->specs['disk_gb'] ?? 40 }}G</div>
                                </td>
                                <td class="small">{{ $service->user?->name }}<div class="sub">{{ $service->user?->email }}</div></td>
                                <td class="small">{{ $service->product?->name ?? '—' }}<div class="sub"><span class="chip">{{ $isMofang ? '魔方上游' : ($isPterodactyl ? '翼龙面板' : '主控') }}</span></div></td>
                                <td class="small">{{ $service->node?->name ?? '—' }}<div class="sub mono">{{ $controllerId ?: strtoupper($service->node?->provider ?? '') }}</div></td>
                                <td class="mono small">{{ $service->ip_address ?: $service->nat_ip ?: '—' }}@if ($service->nat_ip)<div class="sub">{{ $service->nat_port_start }}–{{ $service->nat_port_end }}</div>@endif</td>
                                <td><x-status :status="$service->status" />@if ($service->provision_status && $service->provision_status !== 'success')<div class="sub"><x-status :status="$service->provision_status" /></div>@endif</td>
                                <td class="small muted">{{ $service->expires_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('admin.services.show', $service) }}">管理</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">没有匹配的实例。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $services->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
