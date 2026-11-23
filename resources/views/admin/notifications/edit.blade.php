@extends('layouts.admin')

@section('title', '编辑通知模板')
@section('page-title', '编辑通知模板')

@section('content')
    <div class="page-head">
        <div><h1>编辑通知模板</h1><p>{{ $template->key }} · {{ $template->channel === 'email' ? '邮件' : '短信' }}</p></div>
        <div class="page-actions"><a class="btn btn-ghost" href="{{ route('admin.notifications.index') }}">返回</a></div>
    </div>

    <div class="mail-edit-layout"><div class="panel">
        <div class="panel-head"><h2>模板内容</h2><span class="meta mail-variables">变量：@{{name}} / @{{customer}} / @{{email}} / @{{code}} / @{{expires_minutes}} / @{{service_name}} / @{{service_no}} / @{{date}} / @{{days}} / @{{amount}} / @{{order_no}} / @{{ticket_no}} / @{{sign}}</span></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.notifications.templates.update', $template) }}">
                @csrf @method('PUT')
                <div class="form-field">
                    <label class="form-label">主题（短信可留空）</label>
                    <input class="input" name="subject" value="{{ old('subject', $template->subject) }}">
                </div>
                <div class="form-field mt-3">
                    <label class="form-label">正文内容</label><p class="form-help">普通文本会自动套用品牌邮件样式，保留段落和变量。预览展示已保存的内容。</p>
                    <textarea class="input" name="body" rows="10" required>{{ old('body', $template->body) }}</textarea>
                </div>
                <label class="flex items-center gap-2 mt-3" style="font-size:12px">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $template->enabled))> 启用该模板
                </label>
                <button class="btn btn-primary mt-4" type="submit">保存模板</button>
            </form>
        </div>
    </div>
    @if ($template->channel === 'email')<section class="panel mail-preview-panel"><div class="panel-head"><div><h2>邮件效果预览</h2><span class="meta">示例数据 · 已保存模板</span></div><a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.templates.preview', $template) }}" target="_blank" rel="noopener">单独查看 ↗</a></div><div class="mail-preview-frame"><iframe title="邮件模板预览" sandbox src="{{ route('admin.notifications.templates.preview', $template) }}"></iframe></div>@if ($mailDefinition)<div class="panel-footer"><span class="small muted">触发规则：{{ $mailDefinition['rule'] }}</span></div>@endif</section>@endif
    </div>
@endsection
