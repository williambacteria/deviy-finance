
    <div class="page-head">
        <div><h1>审计日志</h1><p>登录、调账、订单、服务与配置变更的追加记录。</p></div>
        <span class="chip">不可篡改追加日志</span>
    </div>

    <div class="panel" style="margin-bottom:16px">
        <div class="panel-body">
            <form method="GET" class="flex flex-wrap items-end gap-3" action="{{ url()->current() }}">
<input type="hidden" name="tab" value="{{ $workspaceTab ?? '' }}"><input type="hidden" name="section" value="{{ $section ?? '' }}">
                <div class="form-field" style="min-width:220px;flex:1">
                    <label class="form-label" for="q">关键词</label>
                    <input class="input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="动作、描述、管理员">
                </div>
                <div class="form-field" style="min-width:160px">
                    <label class="form-label" for="action">动作</label>
                    <select class="select" id="action" name="action">
                        <option value="">全部动作</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field" style="min-width:180px">
                    <label class="form-label" for="admin_id">管理员</label>
                    <select class="select" id="admin_id" name="admin_id">
                        <option value="">全部管理员</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected((string) ($filters['admin_id'] ?? '') === (string) $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field" style="min-width:140px">
                    <label class="form-label" for="from">开始日期</label>
                    <input class="input" id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="form-field" style="min-width:140px">
                    <label class="form-label" for="to">结束日期</label>
                    <input class="input" id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-primary" type="submit">筛选</button>
                    <a class="btn btn-ghost" href="{{ route('admin.audit.index') }}">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>操作记录</h2><span class="meta">共 {{ $logs->total() }} 条</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>时间</th><th>管理员</th><th>动作</th><th>对象</th><th>描述</th><th>IP</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="small muted">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td class="small">{{ $log->admin?->name ?? '系统' }}</td>
                                <td><span class="chip">{{ $log->action }}</span></td>
                                <td class="mono small">{{ $log->subject_type ? class_basename($log->subject_type).'#'.$log->subject_id : '—' }}</td>
                                <td class="small">{{ $log->description }}</td>
                                <td class="mono small muted">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">没有匹配的审计记录。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $logs->links('partials.pagination') }}</div>
        </div>
    </div>

