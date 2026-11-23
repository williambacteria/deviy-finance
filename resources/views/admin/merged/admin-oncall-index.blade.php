
    <div class="page-head">
        <div><h1>值班表</h1><p>L1/L2 值班安排，用于告警升级通知。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.ops.index') }}">告警中心</a>
    </div>

    <div class="stat-grid">
        @foreach ([1 => 'L1 一线', 2 => 'L2 二线'] as $level => $label)
            <div class="stat-card"><div class="stat-top"><span class="stat-label">{{ $label }}</span></div><div class="stat-value" style="font-size:20px">{{ $current[$level]?->admin?->name ?? '未安排' }}</div><div class="stat-foot">{{ $current[$level] ? $current[$level]->ends_at?->format('m-d H:i').' 结束' : '无当前值班' }}</div></div>
        @endforeach
        <div class="stat-card"><div class="stat-top"><span class="stat-label">值班记录</span></div><div class="stat-value">{{ $shifts->count() }}</div><div class="stat-foot">全部排班</div></div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>新增值班</h2></div>
        <div class="panel-body">
            @if ($errors->any())<div class="note warn" style="margin-bottom:12px">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('admin.oncall.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="form-field" style="min-width:160px"><label class="form-label">管理员</label><select class="select" name="admin_id">@foreach ($admins as $admin)<option value="{{ $admin->id }}">{{ $admin->name }}</option>@endforeach</select></div>
                <div class="form-field" style="min-width:100px"><label class="form-label">级别</label><select class="select" name="level"><option value="1">L1</option><option value="2">L2</option></select></div>
                <div class="form-field" style="min-width:190px"><label class="form-label">开始</label><input class="input" type="datetime-local" name="starts_at" required></div>
                <div class="form-field" style="min-width:190px"><label class="form-label">结束</label><input class="input" type="datetime-local" name="ends_at" required></div>
                <div class="form-field" style="min-width:180px;flex:1"><label class="form-label">备注</label><input class="input" name="note" placeholder="可选"></div>
                <button class="btn btn-primary" type="submit">添加</button>
            </form>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>排班列表</h2></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>级别</th><th>管理员</th><th>开始</th><th>结束</th><th>备注</th><th>操作</th></tr></thead>
                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr>
                                <td><span class="chip">{{ $shift->level === 1 ? 'L1' : 'L2' }}</span></td>
                                <td>{{ $shift->admin?->name }}</td>
                                <td class="small">{{ $shift->starts_at?->format('Y-m-d H:i') }}</td>
                                <td class="small">{{ $shift->ends_at?->format('Y-m-d H:i') }}</td>
                                <td class="small muted">{{ $shift->note ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.oncall.destroy', $shift) }}" data-confirm="确认删除该值班安排？">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">暂无值班安排。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

