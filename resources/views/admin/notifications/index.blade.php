@extends('layouts.admin')

@section('title', '通知记录')
@section('page-title', '通知记录')

@section('content')
    <div class="page-head">
        <div><h1>通知记录</h1><p>邮件、短信发送日志与模板管理。</p></div>
        <div class="page-actions"><a class="btn btn-ghost" href="{{ route('admin.settings.index', ['group' => 'notification']) }}">通知开关</a></div>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-top"><span class="stat-label">发送成功</span><span class="stat-icon"><x-icon name="check" :size="17" /></span></div><div class="stat-value">{{ \App\Models\NotificationLog::query()->where('status', 'sent')->count() }}</div><div class="stat-foot">邮件 + 短信</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">发送失败</span><span class="stat-icon"><x-icon name="alert" :size="17" /></span></div><div class="stat-value">{{ \App\Models\NotificationLog::query()->where('status', 'failed')->count() }}</div><div class="stat-foot">可查看错误原因</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">邮件开关</span><span class="stat-icon"><x-icon name="mail" :size="17" /></span></div><div class="stat-value">{{ ($settings['email_enabled'] ?? 'false') === 'true' ? '开' : '关' }}</div><div class="stat-foot">SMTP / log driver</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">短信开关</span><span class="stat-icon"><x-icon name="chat" :size="17" /></span></div><div class="stat-value">{{ ($settings['sms_enabled'] ?? 'false') === 'true' ? '开' : '关' }}</div><div class="stat-foot">Log / HTTP 网关</div></div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>发送日志</h2><span class="meta">{{ $logs->total() }} 条</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>时间</th><th>渠道</th><th>接收人</th><th>模板</th><th>状态</th><th>错误</th><th>操作</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="small">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                <td><span class="chip">{{ $log->channel === 'email' ? '邮件' : '短信' }}</span></td>
                                <td class="small">{{ $log->user?->name }}<div class="sub mono">{{ $log->recipient }}</div></td>
                                <td class="small mono">{{ $log->template_key ?: '—' }}</td>
                                <td><x-status :status="$log->status" /></td>
                                <td class="small muted" style="max-width:280px">{{ $log->error ? \Illuminate\Support\Str::limit($log->error, 60) : '—' }}<div class="sub">尝试 {{ $log->attempts }} 次{{ $log->next_retry_at ? ' · 下次 '.$log->next_retry_at->format('H:i') : '' }}</div></td>
                                <td>
                                    @if (in_array($log->status, ['pending', 'failed', 'skipped'], true))
                                        <form method="POST" action="{{ route('admin.notifications.retry', $log) }}">
                                            @csrf
                                            <button class="btn btn-soft btn-sm" type="submit">重试</button>
                                        </form>
                                    @else
                                        <span class="small muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">暂无发送记录。开启通知并触发到期提醒后会在这里显示。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())<div class="panel-footer">{{ $logs->links('partials.pagination') }}</div>@endif
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>通知模板</h2><span class="meta">支持 @{{name}} / @{{date}} / @{{days}} 变量</span></div>
        <div class="panel-body">
            @forelse ($templates as $template)
                <div class="flex items-center justify-between gap-3" style="padding:12px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)">
                    <div>
                        <b class="small">{{ $template->key }} · {{ $template->channel === 'email' ? '邮件' : '短信' }}</b>
                        <div class="small muted">{{ $template->subject ?: \Illuminate\Support\Str::limit($template->body, 70) }}</div>
                    </div>
                    <div class="flex gap-2">
                        <x-status :status="$template->enabled ? 'active' : 'closed'" :label="$template->enabled ? '启用' : '停用'" />
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.templates.edit', $template) }}">编辑</a>
                    </div>
                </div>
            @empty
                <div class="small muted">暂无模板。</div>
            @endforelse
        </div>
    </div>
@endsection
