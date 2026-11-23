@extends('layouts.console')

@section('title', '消息通知')
@section('page-title', '消息通知')

@section('content')
    <div class="page-head">
        <div><h1>消息通知</h1><p>实例到期、财务与系统通知。</p></div>
        <div class="page-actions">
            <form method="POST" action="{{ route('console.notices.read-all') }}">
                @csrf
                <button class="btn btn-ghost" type="submit">全部标为已读</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>全部通知</h2><span class="meta">{{ $notices->total() }} 条</span></div>
        <div class="panel-body">
            @forelse ($notices as $notice)
                <div class="notice-row {{ $notice->isRead() ? '' : 'unread' }}">
                    <div class="notice-dot"></div>
                    <div style="flex:1">
                        <div class="flex items-center gap-2" style="flex-wrap:wrap">
                            <b>{{ $notice->title }}</b>
                            @if (! $notice->isRead())
                                <span class="chip">未读</span>
                            @endif
                            <span class="small muted">{{ $notice->created_at?->format('Y-m-d H:i') }}</span>
                        </div>
                        <div class="small muted mt-1">{{ $notice->body }}</div>
                        @if ($notice->data['service_id'] ?? null)
                            <a class="small" style="color:var(--dt-t-2563eb,#2563eb);display:inline-block;margin-top:6px" href="{{ route('console.services.show', $notice->data['service_id']) }}">查看实例</a>
                        @endif
                    </div>
                    @if (! $notice->isRead())
                        <form method="POST" action="{{ route('console.notices.read', $notice) }}">
                            @csrf
                            <button class="btn btn-ghost btn-sm" type="submit">标为已读</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="empty-state">
                    <div class="empty-icon"><x-icon name="bell" :size="21" /></div>
                    <h3>暂无通知</h3>
                    <p>实例到期提醒会出现在这里。</p>
                </div>
            @endforelse
        </div>
        @if ($notices->hasPages())
            <div class="panel-footer">{{ $notices->links('partials.pagination') }}</div>
        @endif
    </div>
@endsection
