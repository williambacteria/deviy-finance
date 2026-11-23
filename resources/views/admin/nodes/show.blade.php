@extends('layouts.admin')

@section('title', '节点详情')
@section('page-title', '节点详情')

@section('content')
    <div class="page-head">
        <div><div class="flex items-center gap-3"><h1>{{ $node->name }}</h1><x-status :status="$node->status" /><span class="chip">{{ strtoupper($node->provider) }}</span></div><p>{{ $node->host }}:{{ $node->port }} · {{ $node->region }} · Agent {{ $node->agent_version }}</p></div>
        <div class="page-actions">
            <form method="POST" action="{{ route('admin.nodes.diagnose', $node) }}">
                @csrf
                <button class="btn btn-primary" type="submit">运行预检</button>
            </form>
            <form method="POST" action="{{ route('admin.nodes.maintenance', $node) }}">
                @csrf
                <button class="btn {{ $node->is_maintenance ? 'btn-primary' : 'btn-ghost' }}" type="submit">{{ $node->is_maintenance ? '退出维护' : '进入维护' }}</button>
            </form>
            <a class="btn btn-ghost" href="{{ route('admin.nodes.index') }}">返回</a>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h2>节点资源</h2></div>
            <div class="panel-body">
                <table class="spec-table">
                    <tr><td>Agent Node ID</td><td class="mono">{{ $node->agent_node_id ?: '未配置' }}</td></tr>
                    <tr><td>Agent URL</td><td class="mono">{{ $node->agent_url ?: '—' }}</td></tr>
                    <tr><td>Agent 模式</td><td><span class="chip">{{ $node->agent_mode }}</span></td></tr>
                    <tr><td>网络巡检策略</td><td>
                        <form method="POST" action="{{ route('admin.nodes.network-policy', $node) }}" class="flex items-center gap-2">
                            @csrf
                            <select class="select" name="network_policy" style="width:auto">
                                <option value="alert" @selected($node->network_policy === 'alert')>仅告警</option>
                                <option value="auto_repair" @selected($node->network_policy === 'auto_repair')>自动修复</option>
                            </select>
                            <button class="btn btn-ghost btn-sm" type="submit">保存</button>
                        </form>
                    </td></tr>
                    <tr><td>证书到期</td><td>{{ $node->cert_expires_at?->format('Y-m-d H:i') ?? '未上报' }} @if($node->cert_expires_at && $node->cert_expires_at->isBefore(now()->addDays(30)))<span class="badge suspended" style="margin-left:6px">即将到期</span>@endif</td></tr>
                    <tr><td>证书指纹</td><td class="mono small">{{ $node->cert_fingerprint ? substr($node->cert_fingerprint, 0, 16).'…' : '未上报' }}</td></tr>
                </table>
                @if ($node->capacity)
                    <div class="meter-list">
                        <div class="meter-row"><div class="meter-head"><span>CPU 核数</span><b>{{ $node->capacity['cpu'] ?? 0 }} 核</b></div><div class="progress"><i style="width:62%"></i></div></div>
                        <div class="meter-row"><div class="meter-head"><span>内存</span><b>{{ $node->capacity['memory_gb'] ?? 0 }} GB</b></div><div class="progress"><i style="width:54%;background:linear-gradient(90deg,var(--dt-b-a78bfa,#a78bfa),var(--dt-b-7c3aed,#7c3aed))"></i></div></div>
                        <div class="meter-row"><div class="meter-head"><span>存储</span><b>{{ $node->capacity['storage_gb'] ?? 0 }} GB</b></div><div class="progress"><i style="width:41%;background:linear-gradient(90deg,var(--dt-b-fbbf24,#fbbf24),var(--dt-b-f59e0b,#f59e0b))"></i></div></div>
                        <div class="meter-row"><div class="meter-head"><span>已用 VM</span><b>{{ $node->capacity['used_vms'] ?? 0 }} 台</b></div><div class="progress"><i style="width:67%;background:linear-gradient(90deg,var(--dt-b-34d399,#34d399),var(--dt-b-059669,#059669))"></i></div></div>
                    </div>
                @else
                    <div class="small muted">Agent 尚未上报容量信息。</div>
                @endif
                <div class="note info mt-4">进入维护后，主控将停止向该节点分配新服务；已有服务不受影响。</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>IP 池</h2></div>
            <div class="panel-body">
                @forelse ($node->ipPools as $pool)
                    <div class="flex justify-between" style="padding:8px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9);font-size:12px"><span>{{ $pool->name }} <span class="muted">{{ $pool->cidr }}</span></span><b>{{ $pool->used }} / {{ $pool->total }}</b></div>
                @empty
                    <div class="small muted">暂无 IP 池。</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head">
            <h2>节点预检</h2>
            @if ($node->diagnostics)
                <span class="badge {{ ($node->diagnostics['status'] ?? '') === 'ok' ? 'active' : ((($node->diagnostics['status'] ?? '') === 'warning') ? 'suspended' : 'failed') }}">
                    {{ ['ok' => '通过', 'warning' => '有警告', 'failed' => '失败'][$node->diagnostics['status'] ?? ''] ?? '未知' }}
                </span>
            @else
                <span class="meta">尚未运行</span>
            @endif
        </div>
        <div class="panel-body">
            @if ($node->diagnostics)
                <div class="small muted mb-3">检查时间：{{ $node->diagnosed_at?->format('Y-m-d H:i:s') }} · Provider：{{ $node->diagnostics['provider'] ?? '—' }} · 节点：{{ $node->diagnostics['node'] ?? '—' }}</div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>检查项</th><th>状态</th><th>说明</th><th class="num">耗时</th></tr></thead>
                        <tbody>
                            @foreach (($node->diagnostics['checks'] ?? []) as $check)
                                <tr>
                                    <td class="mono">{{ $check['name'] ?? '—' }}</td>
                                    <td>
                                        @php($checkStatus = $check['status'] ?? 'warning')
                                        <span class="badge {{ $checkStatus === 'ok' ? 'active' : ($checkStatus === 'failed' ? 'failed' : 'suspended') }}">{{ ['ok' => '通过', 'warning' => '警告', 'failed' => '失败'][$checkStatus] ?? $checkStatus }}</span>
                                    </td>
                                    <td class="small">{{ $check['message'] ?? '—' }}</td>
                                    <td class="num small muted">{{ isset($check['latency_ms']) ? $check['latency_ms'].' ms' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="note info mt-3">预检覆盖 Provider 连通性、流量采集、网络执行层、运行模式与 TLS 状态。</div>
            @else
                <div class="note">点击右上角“运行预检”，通过 Controller 调用 Agent 的 <span class="mono">/api/diagnostics</span>，检查 PVE/Hyper-V 连接、流量采集、网络层与 mTLS 状态。</div>
            @endif
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>节点上的服务</h2></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>实例</th><th>客户</th><th>状态</th><th>IP</th><th>到期</th></tr></thead>
                    <tbody>
                        @forelse ($node->services as $service)
                            <tr><td><a class="strong" href="{{ route('admin.services.show', $service) }}">{{ $service->name }}</a><div class="sub">{{ $service->service_no }}</div></td><td class="small">{{ $service->user?->name }}</td><td><x-status :status="$service->status" /></td><td class="mono small">{{ $service->ip_address ?: $service->nat_ip ?: '—' }}</td><td class="small muted">{{ $service->expires_at?->format('Y-m-d') }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">该节点暂无服务。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
