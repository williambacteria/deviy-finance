@extends('layouts.console')

@section('title', '工单')
@section('page-title', '工单')

@section('content')
    <div class="page-head">
        <div><h1>工单</h1><p>技术、财务与销售问题都可以在这里提交。</p></div>
        <a class="btn btn-primary" href="{{ route('console.tickets.create') }}"><x-icon name="plus" :size="15" /> 新建工单</a>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>主题</th><th>部门</th><th>优先级</th><th>状态</th><th>最后回复</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr>
                                <td><a class="strong" href="{{ route('console.tickets.show', $ticket) }}">{{ $ticket->subject }}</a><div class="sub">{{ $ticket->ticket_no }}</div></td>
                                <td class="small">{{ ['technical' => '技术', 'billing' => '财务', 'sales' => '销售', 'other' => '其他'][$ticket->department] ?? $ticket->department }}</td>
                                <td><span class="chip">{{ ['low' => '低', 'normal' => '普通', 'high' => '高', 'urgent' => '紧急'][$ticket->priority] ?? $ticket->priority }}</span></td>
                                <td><x-status :status="$ticket->status" /></td>
                                <td class="small muted">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                                <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('console.tickets.show', $ticket) }}">查看</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">还没有工单。遇到问题随时提交。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $tickets->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
