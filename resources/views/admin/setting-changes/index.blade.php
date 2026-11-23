@extends('layouts.admin')

@section('title', '设置变更审批')
@section('page-title', '设置变更审批')

@section('content')
    <div class="page-head">
        <div><h1>设置变更审批</h1><p>附件配额等高风险设置变更超过阈值后，需超级管理员批准。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.settings.index', ['group' => 'attachment']) }}">附件配额设置</a>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>待审批</h2><span class="meta">{{ $pending->count() }} 项</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>提交人</th><th>设置项</th><th>原值</th><th>新值</th><th>审批进度</th><th>时间</th><th>操作</th></tr></thead>
                    <tbody>
                        @forelse ($pending as $change)
                            <tr>
                                <td class="small">{{ $change->admin?->name ?? '—' }}</td>
                                <td class="mono small">{{ $change->group_name }}.{{ $change->key }}</td>
                                <td class="small muted">{{ $change->old_value ?? '—' }}</td>
                                <td class="small"><b>{{ $change->new_value }}</b></td>
                                <td class="small">{{ $change->approvals_count }} / {{ $requiredApprovals }}</td>
                                <td class="small muted">{{ $change->created_at?->format('m-d H:i') }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('admin.settings.changes.approve', $change) }}">
                                            @csrf
                                            <button class="btn btn-primary btn-sm" type="submit">批准</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.settings.changes.reject', $change) }}" class="flex gap-2">
                                            @csrf
                                            <input class="input" name="reason" placeholder="拒绝原因" style="width:150px">
                                            <button class="btn btn-danger btn-sm" type="submit">拒绝</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">暂无待审批变更。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>审批历史</h2></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>时间</th><th>设置项</th><th>原值 → 新值</th><th>结果</th><th>审批人</th></tr></thead>
                    <tbody>
                        @forelse ($history as $change)
                            <tr>
                                <td class="small muted">{{ $change->reviewed_at?->format('Y-m-d H:i') }}</td>
                                <td class="mono small">{{ $change->group_name }}.{{ $change->key }}</td>
                                <td class="small">{{ $change->old_value ?? '—' }} → {{ $change->new_value }}</td>
                                <td><span class="badge {{ $change->status === 'approved' ? 'active' : 'failed' }}">{{ $change->status === 'approved' ? '已批准' : '已拒绝' }}</span></td>
                                <td class="small">{{ $change->reviewer?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">暂无审批历史。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $history->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
