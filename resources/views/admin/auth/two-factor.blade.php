@extends('layouts.auth')

@section('title', '管理员双重验证')

@section('content')
    <h2>双重验证</h2>
    <p class="lead">请输入验证器中的 6 位动态码，或使用一枚恢复码。</p>

    @if ($errors->any())
        <div class="note warn" style="margin-bottom:16px">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.two-factor.store') }}">
        @csrf
        <div class="form-field">
            <label class="form-label" for="code">动态验证码</label>
            <input class="input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" autofocus>
        </div>
        <div class="form-field mt-4">
            <label class="form-label" for="recovery_code">或使用恢复码</label>
            <input class="input" id="recovery_code" name="recovery_code" autocomplete="off" maxlength="32" placeholder="XXXX-XXXX">
        </div>
        <button class="btn btn-primary btn-block mt-6" type="submit">验证并进入后台</button>
    </form>

    <div class="auth-hint">动态码每 30 秒更新；恢复码使用一次后失效。</div>
@endsection
