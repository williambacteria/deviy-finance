@extends('layouts.admin')

@section('title', '节点管理')
@section('page-title', '节点管理')

@section('content')
    <div class="page-head">
        <div><h1>节点映射</h1><p>这里只做业务侧节点映射与巡检；宿主机连接、VM 创建、镜像和 IP 池请使用 Controller 主控台。</p></div>
        <span class="chip">mTLS Agent Hub · 15s heartbeat</span>
    </div>

    <div class="note info" style="margin-bottom:14px">宿主机管理入口已迁移到 Go Controller Web：<b>http://127.0.0.1:8088</b>（生产通过受保护的反向代理访问）。</div>

    <div class="grid-3">
        @foreach ($nodes as $node)
            <div class="panel">
                <div class="panel-head">
                    <div><h2>{{ $node->name }}</h2><div class="small muted">{{ strtoupper($node->provider) }} · {{ $node->host }}:{{ $node->port }}</div></div>
                    <x-status :status="$node->status" />
                </div>
                <div class="panel-body">
                    <div class="flex justify-between small" style="padding:5px 0"><span class="muted">地域</span><b>{{ $node->region }}</b></div>
                    <div class="flex justify-between small" style="padding:5px 0"><span class="muted">服务数</span><b>{{ $node->services_count }}</b></div>
                    <div class="flex justify-between small" style="padding:5px 0"><span class="muted">Agent</span><b class="mono">{{ $node->agent_version ?? '—' }}</b></div>
                    <div class="flex justify-between small" style="padding:5px 0"><span class="muted">心跳</span><b>{{ $node->last_heartbeat_at?->diffForHumans() ?? '—' }}</b></div>
                    @if ($node->capacity)
                        <div class="progress mt-3"><i style="width:{{ min(100, round(($node->capacity['used_vms'] ?? 0) / max(1, $node->capacity['cpu'] ?? 1) * 100)) }}%"></i></div>
                        <div class="small muted mt-2">已用 {{ $node->capacity['used_vms'] ?? 0 }} 台 / CPU {{ $node->capacity['cpu'] ?? 0 }} 核</div>
                    @endif
                    <div class="flex gap-2 mt-3">
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.nodes.show', $node) }}">详情</a>
                        @if ($node->is_maintenance)<span class="badge pending">维护中</span>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
