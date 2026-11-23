@extends('layouts.auth')
@section('title', '重设密码')
@section('content')
    <div class="recovery-heading"><span><x-icon name="shield" :size="25" /></span><small>账户安全</small><h2>设置新的登录密码</h2><p class="lead">使用独立的新密码，保护你的账户与云资源。</p></div>
    @if ($errors->any())<div class="note danger recovery-notice" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('password.update') }}" data-recovery-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-field"><label class="form-label" for="reset-email">注册邮箱</label><input class="input" id="reset-email" type="email" name="email" value="{{ old('email', $email) }}" maxlength="190" autocomplete="email" required></div>
        <div class="form-field mt-4"><label class="form-label" for="reset-password">新密码</label><div class="recovery-password"><input class="input" id="reset-password" name="password" type="password" minlength="10" maxlength="128" autocomplete="new-password" required><button type="button" data-password-toggle="reset-password" aria-label="显示新密码" aria-pressed="false">显示</button></div><p class="recovery-field-help">至少 10 位，包含字母和数字。建议使用不同于其他网站的密码。</p></div>
        <div class="form-field mt-4"><label class="form-label" for="reset-confirm">确认新密码</label><div class="recovery-password"><input class="input" id="reset-confirm" name="password_confirmation" type="password" minlength="10" maxlength="128" autocomplete="new-password" required><button type="button" data-password-toggle="reset-confirm" aria-label="显示确认密码" aria-pressed="false">显示</button></div></div>
        <button class="btn btn-primary btn-block recovery-submit" type="submit">保存新密码</button><p class="recovery-delivery" role="status" data-recovery-status></p>
    </form>
    <div class="recovery-help"><x-icon name="lock" :size="18" /><div><b>重设后需要重新登录</b><p>旧登录状态会失效。链接过期或已经使用？可以重新申请找回邮件。</p></div></div>
    <div class="auth-links"><a href="{{ route('login') }}">← 返回登录</a><a href="{{ route('password.request') }}">重新获取链接</a></div>
@endsection
