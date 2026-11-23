@extends('layouts.auth')

@section('title', '注册')

@section('content')
    <h2>创建云账户</h2>
    <p class="lead">准备一个常用邮箱，即可开始注册。</p>
    <ol class="auth-register-steps" aria-label="注册流程"><li aria-current="step"><b>1</b>填写资料</li><li><b>2</b>验证邮箱</li><li><b>3</b>开始使用</li></ol>

    @if ($errors->any())
        <div class="note danger" role="alert" style="margin-bottom:18px">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('register.store') }}" class="auth-register-form">
        @csrf
        <div class="form-grid">
            <div class="form-field full"><label class="form-label" for="name">昵称 <span aria-hidden="true">*</span></label><input class="input" id="name" name="name" value="{{ old('name') }}" placeholder="希望我们如何称呼你" autocomplete="nickname" maxlength="80" required></div>
            <div class="form-field full"><label class="form-label" for="email">邮箱 <span aria-hidden="true">*</span></label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" maxlength="255" required><small class="register-field-hint">用于账户登录与接收邮箱验证邮件。</small></div>
            <div class="form-field"><label class="form-label" for="password">密码 <span aria-hidden="true">*</span></label><div class="password-field"><input class="input" id="password" name="password" type="password" placeholder="至少 8 位" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-password-toggle aria-label="显示密码"><span data-password-eye-show><x-icon name="eye" :size="18" /></span><span data-password-eye-hide hidden><x-icon name="eye-off" :size="18" /></span></button></div></div>
            <div class="form-field"><label class="form-label" for="password_confirmation">确认密码 <span aria-hidden="true">*</span></label><div class="password-field"><input class="input" id="password_confirmation" name="password_confirmation" type="password" placeholder="再次输入密码" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-password-toggle aria-label="显示密码"><span data-password-eye-show><x-icon name="eye" :size="18" /></span><span data-password-eye-hide hidden><x-icon name="eye-off" :size="18" /></span></button></div></div>
        </div>
        <details class="register-optional" @if ($errors->hasAny(['phone', 'qq', 'company']) || old('phone') || old('qq') || old('company')) open @endif><summary>补充联系资料 <span>选填</span><x-icon name="chevron-right" :size="16" /></summary><div class="form-grid"><div class="form-field"><label class="form-label" for="phone">手机号 <small>选填</small></label><input class="input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="便于服务联系" autocomplete="tel" maxlength="32"></div><div class="form-field"><label class="form-label" for="qq">QQ 号 <small>选填</small></label><input class="input" id="qq" name="qq" inputmode="numeric" value="{{ old('qq') }}" placeholder="填写你的 QQ 号" maxlength="32"></div><div class="form-field full"><label class="form-label" for="company">公司 / 团队 <small>选填</small></label><input class="input" id="company" name="company" value="{{ old('company') }}" placeholder="公司或团队名称" autocomplete="organization" maxlength="120"></div></div></details>
        <div class="register-verification"><span class="form-label">安全验证</span>@include('xf-captcha::captcha', ['inputName' => 'xf_captcha', 'placeholder' => '点击完成点选验证', 'width' => '100%', 'height' => '46px'])</div>
        <p class="auth-legal-note">点击创建账户，即表示你已阅读并同意<a href="{{ route('terms') }}" target="_blank" rel="noopener">《服务条款》</a>和<a href="{{ route('privacy') }}" target="_blank" rel="noopener">《隐私政策》</a>。</p>
        <button class="btn btn-primary btn-block" type="submit">创建账户 <x-icon name="arrow-right" :size="17" /></button>
    </form>
    <div class="auth-links register-login-link"><span>已有账户？</span><a href="{{ route('login') }}">直接登录 →</a></div>
@endsection

