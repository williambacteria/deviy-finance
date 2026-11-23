
    <div class="page-head">
        <div><h1>告警中心</h1><p>网络、财务、开通与审批异常统一待办，支持认领、静默与解决。</p></div>
        <form method="POST" action="{{ route('admin.ops.scan') }}">
            @csrf
            <button class="btn btn-primary" type="submit">立即扫描</button>
        </form>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-top"><span class="stat-label">未解决</span></div><div class="stat-value">{{ $stats['open'] }}</div><div class="stat-foot">open / acked / silenced</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">严重</span></div><div class="stat-value">{{ $stats['critical'] }}</div><div class="stat-foot">critical</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">已认领</span></div><div class="stat-value">{{ $stats['acked'] }}</div><div class="stat-foot">处理中</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">已解决</span></div><div class="stat-value">{{ $stats['resolved'] }}</div><div class="stat-foot">历史累计</div></div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head">
            <h2>告警列表</h2>
            <form method="GET" class="flex gap-2" action="{{ url()->current() }}">
<input type="hidden" name="tab" value="{{ $workspaceTab ?? '' }}"><input type="hidden" name="section" value="{{ $section ?? '' }}">
                <select class="select" name="status" style="width:auto" onchange="this.form.submit()">
                    <option value="open" @selected($status === 'open')>未解决</option>
                    <option value="resolved" @selected($status === 'resolved')>已解决</option>
                </select>
                <select class="select" name="severity" style="width:auto" onchange="this.form.submit()">
                    <option value="">全部级别</option>
                    <option value="critical" @selected($severity === 'critical')>严重</option>
                    <option value="warning" @selected($severity === 'warning')>警告</option>
                </select>
            </form>
        </div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>级别</th><th>来源</th><th>告警</th><th>状态</th><th>次数</th><th>认领</th><th>操作</th></tr></thead>
                    <tbody>
                        @forelse ($alerts as $alert)
                            <tr>
                                <td><span class="badge {{ $alert->severity === 'critical' ? 'failed' : 'suspended' }}">{{ $alert->severity === 'critical' ? '严重' : '警告' }}</span></td>
                                <td><span class="chip">{{ $alert->source }}</span></td>
                                <td>
                                    <div class="strong small">{{ $alert->title }}</div>
                                    <div class="small muted" style="margin-top:4px">{{ $alert->body }}</div>
                                    <div class="small muted" style="margin-top:3px">{{ $alert->last_seen_at?->format('m-d H:i') }} · {{ $alert->key }}</div>
                                </td>
                                <td>
                                    @if ($alert->status === 'resolved')
                                        <span class="badge active">已解决</span>
                                    @elseif ($alert->status === 'silenced')
                                        <span class="badge suspended">静默至 {{ $alert->silenced_until?->format('m-d H:i') }}</span>
                                    @elseif ($alert->status === 'acked')
                                        <span class="badge info">处理中</span>
                                    @else
                                        <span class="badge failed">未处理</span>
                                    @endif
                                    @if ($alert->escalated_at)<span class="badge failed" style="margin-left:4px">已升级</span>@endif
                                </td>
                                <td class="small">{{ $alert->occurrences }}</td>
                                <td class="small">{{ $alert->assignedAdmin?->name ?? '—' }}</td>
                                <td>
                                    @if ($alert->status !== 'resolved')
                                        <div class="flex gap-2" style="flex-wrap:wrap">
                                            <form method="POST" action="{{ route('admin.ops.ack', $alert) }}">@csrf<button class="btn btn-soft btn-sm" type="submit">认领</button></form>
                                            <form method="POST" action="{{ route('admin.ops.silence', $alert) }}" class="flex gap-1">@csrf
                                                <select class="select" name="hours" style="width:72px"><option value="1">1h</option><option value="6">6h</option><option value="24">24h</option></select>
                                                <button class="btn btn-ghost btn-sm" type="submit">静默</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.ops.resolve', $alert) }}">@csrf<button class="btn btn-primary btn-sm" type="submit">解决</button></form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">暂无告警。可点击“立即扫描”。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $alerts->links('partials.pagination') }}</div>
        </div>
    </div>

