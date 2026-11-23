@extends('layouts.admin')

@section('title', '网络巡检')
@section('page-title', '网络巡检')

@section('content')
    <div class="page-head">
        <div><h1>网络巡检</h1><p>定时回读各节点 NAT 实际规则，与期望规则比对。</p></div>
        <div class="page-actions">
            <form method="POST" action="{{ route('admin.network-drift.check') }}">
                @csrf
                <button class="btn btn-primary" type="submit">立即巡检</button>
            </form>
            <a class="btn btn-ghost" href="{{ route('admin.controller.index') }}">节点管理</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-top"><span class="stat-label">正常</span></div><div class="stat-value">{{ $stats['clean'] }}</div><div class="stat-foot">规则完全一致</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">漂移</span></div><div class="stat-value">{{ $stats['drift'] }}</div><div class="stat-foot">缺失 {{ $stats['missing'] }} · 多余 {{ $stats['extra'] }}</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">巡检异常</span></div><div class="stat-value">{{ $stats['error'] }}</div><div class="stat-foot">Agent / 网络不可达</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">巡检节点</span></div><div class="stat-value">{{ $checks->count() }}</div><div class="stat-foot">最近一次结果</div></div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>节点巡检结果</h2><span class="meta">每 10 分钟自动巡检</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>节点</th><th>状态</th><th>处理</th><th>期望 / 实际</th><th>缺失</th><th>多余</th><th>最后检查</th><th>错误</th></tr></thead>
                    <tbody>
                        @forelse ($checks as $check)
                            <tr>
                                <td><a class="strong" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ $check->node?->agent_node_id ? route('admin.controller.show', $check->node->agent_node_id) : route('admin.controller.index') }}">{{ $check->node?->name }}</a><div class="sub">{{ $check->node?->region }}</div></td>
                                <td>
                                    @if ($check->status === 'clean')
                                        <span class="badge active">正常</span>
                                    @elseif ($check->status === 'drift')
                                        <span class="badge failed">漂移</span>
                                    @elseif ($check->status === 'error')
                                        <span class="badge suspended">异常</span>
                                    @else
                                        <span class="badge">未巡检</span>
                                    @endif
                                </td>
                                <td><span class="chip">{{ ['none' => '未处理', 'alert' => '仅告警', 'auto_repair' => '自动修复', 'approval' => '待审批', 'manual_repair' => '人工修复'][$check->action] ?? $check->action }}</span>
                                    @if ($check->status === 'drift')
                                        <form method="POST" action="{{ route('admin.network-drift.repair', $check) }}" style="display:inline" data-confirm="确认按当前期望规则修复该节点网络？">
                                            @csrf
                                            <button class="btn btn-soft btn-sm" type="submit">批准修复</button>
                                        </form>
                                    @endif
                                </td>
                                <td>{{ $check->expected_count }} / {{ $check->actual_count }}</td>
                                <td class="small">
                                    @forelse (($check->missing ?? []) as $item)
                                        <div class="mono">{{ $item }}</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td class="small">
                                    @forelse (($check->extra ?? []) as $item)
                                        <div class="mono">{{ $item }}</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td class="small muted">{{ $check->checked_at?->format('m-d H:i:s') ?? '—' }}</td>
                                <td class="small muted" style="max-width:220px">{{ $check->last_error ? \Illuminate\Support\Str::limit($check->last_error, 60) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">暂无巡检记录。点击“立即巡检”或等待定时任务。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
