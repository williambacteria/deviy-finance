@extends('layouts.admin')

@section('title', '管理员消息')
@section('page-title', '管理员消息')

@section('content')
    <div class="page-head">
        <div><h1>管理员消息</h1><p>审批、系统提醒等运营待办。</p></div>
        <form method="POST" action="{{ route('admin.inbox.read-all') }}">
            @csrf
            <button class="btn btn-ghost" type="submit">全部标为已读</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>收件箱</h2><span class="meta">{{ $unread }} 条未读</span></div>
        <div class="panel-body">
            @forelse ($notices as $notice)
                <div class="flex items-start justify-between gap-4" style="padding:14px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)">
                    <div>
                        <div class="flex items-center gap-2">
                            <b class="small">{{ $notice->title }}</b>
                            @if (! $notice->read_at)<span class="badge info" style="height:20px;font-size:10px">未读</span>@endif
                        </div>
                        <div class="small muted" style="margin-top:6px;line-height:1.7">{{ $notice->body }}</div>
                        <div class="small muted" style="margin-top:4px">{{ $notice->created_at?->format('Y-m-d H:i') }}</div>
                    </div>
                    @if (! $notice->read_at)
                        <form method="POST" action="{{ route('admin.inbox.read', $notice) }}">
                            @csrf
                            <button class="btn btn-soft btn-sm" type="submit">标为已读</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="small muted">暂无消息。</div>
            @endforelse
            <div class="mt-4">{{ $notices->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
