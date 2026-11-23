@extends('layouts.auth')

@section('title', '登录')

@section('content')
    <h2>欢迎回来</h2>
    <p class="lead">登录后可查看实例状态、续费账单和工单进度。</p>

    @if ($errors->any())
        <div class="note danger" role="alert" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="note info" role="status" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="form-field">
            <label class="form-label" for="email">邮箱</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="请输入注册邮箱" autocomplete="username" required>
        </div>
        <div class="form-field mt-4">
            <label class="form-label" for="password">密码</label>
            <div class="password-field">
                <input class="input" id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle aria-label="显示密码"><span data-password-eye-show><x-icon name="eye" :size="18" /></span><span data-password-eye-hide hidden><x-icon name="eye-off" :size="18" /></span></button>
            </div>
        </div>
        <div class="flex items-center justify-between mt-4">
            <label class="checkbox"><input type="checkbox" name="remember" value="1"> 记住我</label>
            <a class="auth-forgot" href="{{ route('password.request') }}">忘记密码？</a>
        </div>
        <div class="auth-login-verification">
            <span class="form-label">安全验证</span>
            @include('xf-captcha::captcha', ['inputName' => 'xf_captcha', 'placeholder' => '点击完成点选验证', 'width' => '100%', 'height' => '46px'])
        </div>
        <button class="btn btn-primary btn-block mt-6" type="submit">登录控制台</button>
    </form>

    <div class="auth-links">
        <span>还没有账户？</span>
        <a href="{{ route('register') }}">免费注册</a>
    </div>
@endsection
