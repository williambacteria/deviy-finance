@extends('layouts.auth')

@section('title', '退订通知')

@section('content')
    <div class="auth-card" style="max-width:460px">
        <div class="auth-brand">Deviy Cloud</div>
        <h1>已退订</h1>
        <p class="muted small">
            {{ $user->name }}，你已退订
            <b>{{ $type === 'service_due' ? '实例到期提醒' : $type }}</b>
            的{{ $channel === 'email' ? '邮件' : '短信' }}通知。
        </p>
        <p class="muted small">你仍可以在控制台「账户设置 → 通知偏好」中重新开启。</p>
        <a class="btn btn-primary btn-block mt-4" href="{{ route('console.settings') }}">返回控制台</a>
    </div>
@endsection
