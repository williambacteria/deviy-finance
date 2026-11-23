@extends('layouts.admin')

@section('title', '邮件通知插件')
@section('page-title', '邮件通知插件')

@section('content')
    <div class="page-head">
        <div><div class="flex items-center gap-3"><h1>邮件通知插件</h1><span class="chip">阿里云 SMTP</span></div><p>注册验证、账号欢迎、服务器开通提醒与模板管理。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.plugins.index') }}">返回插件</a>
    </div>

    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h2>阿里云 SMTP</h2><span class="meta">DirectMail</span></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.mail.update') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="form-field"><label class="form-label">SMTP 地址</label><input class="input" name="host" value="{{ old('host', $config['host']) }}" required></div>
                        <div class="form-field"><label class="form-label">端口</label><input class="input" type="number" name="port" value="{{ old('port', $config['port']) }}" required></div>
                        <div class="form-field"><label class="form-label">加密方式</label><select class="select" name="encryption">@foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'starttls' => 'STARTTLS', 'none' => '无'] as $value => $label)<option value="{{ $value }}" @selected($config['encryption'] === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">SMTP 用户名</label><input class="input" name="username" value="{{ old('username', $config['username']) }}" required></div>
                        <div class="form-field full"><label class="form-label">SMTP 密码</label><input class="input" type="password" name="password" value="{{ old('password', $config['password']) }}" required></div>
                        <div class="form-field"><label class="form-label">发件地址</label><input class="input" type="email" name="from_address" value="{{ old('from_address', $config['from_address']) }}" required></div>
                        <div class="form-field"><label class="form-label">发件名称</label><input class="input" name="from_name" value="{{ old('from_name', $config['from_name']) }}" required></div>
                        <div class="form-field full"><label class="flex items-center gap-2" style="font-size:12px"><input type="checkbox" name="is_enabled" value="1" @checked($plugin->is_enabled)> 启用邮件通知</label></div>
                    </div>
                    <button class="btn btn-primary" type="submit" style="margin-top:14px">保存配置</button>
                </form>

                <div style="border-top:1px solid var(--dt-e-edf2f9,#edf2f9);margin-top:18px;padding-top:16px">
                    <form method="POST" action="{{ route('admin.mail.test') }}" class="filter-bar">
                        @csrf
                        <div class="form-field" style="flex:1"><label class="form-label">测试收件邮箱</label><input class="input" type="email" name="test_email" value="{{ auth('admin')->user()->email }}" required></div>
                        <button class="btn btn-soft" type="submit">发送测试邮件</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>邮件模板</h2><span class="meta">可自定义编辑</span></div>
            <div class="panel-body">
                @foreach ($templates as $template)
                    <div class="flex items-center justify-between gap-3" style="padding:11px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)">
                        <div><b class="small">{{ $template->key }}</b><div class="small muted">{{ \Illuminate\Support\Str::limit($template->subject ?: $template->body, 70) }}</div></div>
                        <div class="flex items-center gap-2">
                            <x-status :status="$template->enabled ? 'active' : 'closed'" />
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.templates.edit', $template) }}">编辑</a>
                        </div>
                    </div>
                @endforeach
                <div class="note info" style="margin-top:14px">模板变量：@{{customer}}、@{{email}}、@{{code}}、@{{expires_minutes}}、@{{login_url}}、@{{service_name}}、@{{service_no}}、@{{console_url}}、@{{sign}}</div>
            </div>
        </div>
    </div>
@endsection
