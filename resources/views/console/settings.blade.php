@extends('layouts.console')

@section('title', '账户设置')
@section('page-title', '账户设置')

@section('content')
    <div class="page-head"><div><h1>账户设置</h1><p>维护联系方式、安全设置与登录密码。</p></div></div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h2>基本资料</h2></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('console.settings.update') }}">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        <div class="form-field"><label class="form-label">姓名</label><input class="input" name="name" value="{{ old('name', $user->name) }}" required></div>
                        <div class="form-field"><label class="form-label">邮箱</label><input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                        <div class="form-field"><label class="form-label">手机号</label><input class="input" name="phone" value="{{ old('phone', $user->phone) }}"></div>
                        <div class="form-field"><label class="form-label">QQ号</label><input class="input" name="qq" value="{{ old('qq', $user->qq) }}" placeholder="用于客服联系"></div>
                        <div class="form-field"><label class="form-label">公司 / 团队</label><input class="input" name="company" value="{{ old('company', $user->company) }}"></div>
                        <div class="form-field"><label class="form-label">免打扰开始</label><input class="input" type="time" name="quiet_hours_start" value="{{ old('quiet_hours_start', $user->quiet_hours_start ? substr($user->quiet_hours_start, 0, 5) : '') }}"></div>
                        <div class="form-field"><label class="form-label">免打扰结束</label><input class="input" type="time" name="quiet_hours_end" value="{{ old('quiet_hours_end', $user->quiet_hours_end ? substr($user->quiet_hours_end, 0, 5) : '') }}"></div>
                    </div>
                    <button class="btn btn-primary mt-4" type="submit">保存资料</button>
                </form>
            </div>
        </div>

        <div style="display:grid;gap:16px">
            <div class="panel">
                <div class="panel-head"><h2>账户状态</h2></div>
                <div class="panel-body">
                    <table class="spec-table">
                        <tr><td>客户分组</td><td>{{ $user->customer_group === 'vip' ? 'VIP 客户' : '标准客户' }}</td></tr>
                        <tr><td>实名认证</td><td>{{ ['verified' => '已认证', 'pending' => '审核中', 'rejected' => '未通过'][$user->real_name_status] ?? '未认证' }} · <a href="{{ route('console.real-name') }}">实名认证</a></td></tr>
                        <tr><td>账户余额</td><td>{{ \App\Support\Money::format($user->balance) }}</td></tr>
                        <tr><td>信用额度</td><td>{{ \App\Support\Money::format($user->credit_limit) }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>修改密码</h2></div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('console.settings.password') }}">
                        @csrf @method('PUT')
                        <div class="form-field"><label class="form-label">当前密码</label><input class="input" type="password" name="current_password" required></div>
                        <div class="form-field mt-3"><label class="form-label">新密码</label><input class="input" type="password" name="password" required></div>
                        <div class="form-field mt-3"><label class="form-label">确认新密码</label><input class="input" type="password" name="password_confirmation" required></div>
                        <button class="btn btn-ghost mt-4" type="submit">更新密码</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>通知偏好</h2><span class="meta">按渠道退订，不影响站内通知</span></div>
        <div class="panel-body">
            @php($preferences = $user->notificationPreferences->keyBy(fn ($item) => $item->channel.'.'.$item->type))
            <form method="POST" action="{{ route('console.settings.notifications') }}">
                @csrf @method('PUT')
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>通知类型</th><th>邮件</th><th>短信</th></tr></thead>
                        <tbody>
                            @foreach ($notificationTypes as $type => $label)
                                <tr>
                                    <td><b class="small">{{ $label }}</b><div class="sub">{{ $type }}</div></td>
                                    @foreach (['email' => '邮件', 'sms' => '短信'] as $channel => $channelLabel)
                                        <td>
                                            <label class="flex items-center gap-2" style="font-size:12px">
                                                <input type="checkbox" name="preferences[{{ $channel }}.{{ $type }}]" value="1" @checked($preferences->get($channel.'.'.$type)?->enabled ?? true)>
                                                接收{{ $channelLabel }}
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary mt-4" type="submit">保存通知偏好</button>
            </form>
            <div class="note mt-3">邮件底部也提供一键退订链接；退订只影响该渠道，站内通知不受影响。</div>
        </div>
    </div>
@endsection
