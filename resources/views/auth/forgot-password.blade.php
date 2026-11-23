@extends('layouts.auth')
@section('title', '找回密码')
@section('content')
    <div class="recovery-heading"><span><x-icon name="key" :size="25" /></span><small>账户安全</small><h2>找回你的账户</h2><p class="lead">通过注册邮箱接收安全链接，重新设置登录密码。</p></div>
    @if ($errors->any())<div class="note danger recovery-notice" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="note info recovery-notice" role="status">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" data-recovery-form>
        @csrf
        <div class="form-field"><label class="form-label" for="recovery-email">注册邮箱</label><input class="input" id="recovery-email" type="email" name="email" value="{{ old('email') }}" maxlength="190" placeholder="you@example.com" autocomplete="email" required autofocus></div>
        @include('xf-captcha::captcha', ['inputName' => 'xf_captcha', 'placeholder' => '点击完成点选验证', 'width' => '100%', 'height' => '40px'])
        <button class="btn btn-primary btn-block recovery-submit" type="submit">发送重设密码邮件 <span aria-hidden="true">→</span></button>
        <p class="recovery-delivery" role="status" data-recovery-status></p>
    </form>
    <div class="recovery-help"><x-icon name="mail" :size="18" /><div><b>没有收到邮件？</b><p>检查垃圾邮件与邮箱拼写。重设链接 {{ config('auth.passwords.users.expire', 60) }} 分钟内有效，仅可使用一次；若无法访问注册邮箱，请联系 <a href="mailto:admin@deviy.cn">admin@deviy.cn</a> 核验账户。</p></div></div>
    <div class="auth-links"><a href="{{ route('login') }}">← 返回登录</a><a href="{{ route('register') }}">注册新账户</a></div>
@endsection
