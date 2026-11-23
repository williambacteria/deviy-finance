@extends('layouts.auth')

@section('title', '验证邮箱')

@section('content')
    <h2>验证你的邮箱</h2>
    <p class="lead">验证码已发送至 {{ auth()->user()->email }}，请输入邮件中的 6 位验证码。</p>

    @if (session('status'))
        <div class="note info" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="note danger" style="margin-bottom:14px">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('verification.code') }}" data-code-form>
        @csrf
        <div class="form-field">
            <label class="form-label" for="verification-code-1">邮箱验证码</label>
            <div class="verification-code" data-verification-code>
                @for ($i = 1; $i <= 6; $i++)
                    <input class="code-digit" id="verification-code-{{ $i }}" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}" aria-label="验证码第 {{ $i }} 位" data-code-digit @if ($i === 1) autofocus @endif>
                @endfor
                <input type="hidden" name="code" value="{{ old('code') }}" data-code-value>
            </div>
        </div>
        <button class="btn btn-primary btn-block mt-4" type="submit">验证邮箱</button>
    </form>

    <div class="note info" style="margin-top:16px">验证码 10 分钟内有效。没有收到？请检查垃圾邮件，或点击下方重新发送。</div>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="btn btn-ghost btn-block" type="submit">重新发送验证码</button>
    </form>

    <div class="auth-links">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-ghost btn-block" type="submit">退出登录</button>
        </form>
    </div>

    <script src="{{ asset('assets/js/email-code.js') }}" defer></script>
@endsection
