@extends('layouts.admin')

@section('title', '工单管理')
@section('page-title', '工单管理')

@section('content')
    <div class="page-head">
        <div><h1>工单管理</h1><p>客户问题、SLA 与处理记录。</p></div>
        <div class="page-actions">
            <form method="GET" class="filter-bar">
                <select class="select" name="status"><option value="">全部状态</option>@foreach (['open' => '待回复', 'pending' => '处理中', 'resolved' => '已解决', 'closed' => '已关闭'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
                <select class="select" name="priority"><option value="">全部优先级</option>@foreach (['urgent' => '紧急', 'high' => '高', 'normal' => '普通', 'low' => '低'] as $value => $label)<option value="{{ $value }}" @selected($priority === $value)>{{ $label }}</option>@endforeach</select>
                <button class="btn btn-ghost" type="submit">筛选</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>工单</th><th>客户</th><th>部门</th><th>优先级</th><th>状态</th><th>处理人</th><th>最后回复</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr>
                                <td><a class="strong" href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->subject }}</a><div class="sub">{{ $ticket->ticket_no }}</div></td>
                                <td class="small">{{ $ticket->user?->name }}</td>
                                <td class="small">{{ ['technical' => '技术', 'billing' => '财务', 'sales' => '销售', 'other' => '其他'][$ticket->department] ?? $ticket->department }}</td>
                                <td><span class="chip">{{ ['urgent' => '紧急', 'high' => '高', 'normal' => '普通', 'low' => '低'][$ticket->priority] ?? $ticket->priority }}</span></td>
                                <td><x-status :status="$ticket->status" /></td>
                                <td class="small">{{ $ticket->assignedAdmin?->name ?? '待分配' }}</td>
                                <td class="small muted">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                                <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('admin.tickets.show', $ticket) }}">处理</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">没有匹配的工单。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $tickets->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
