@php
    $isRegister = request()->routeIs('register');
    $showcase = $isRegister || request()->routeIs('login');
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ filemtime(public_path('assets/js/theme-init.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/fonts/noto-sc/noto-sc.css') }}?v=20261117">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '登录') · {{ $siteSettings['site_name'] ?? 'Deviy Cloud' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/azure.css') }}?v={{ filemtime(public_path('assets/css/azure.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v={{ filemtime(public_path('assets/css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
    @if (request()->routeIs('password.*'))
        <link rel="stylesheet" href="{{ asset('assets/css/password-recovery.css') }}?v={{ filemtime(public_path('assets/css/password-recovery.css')) }}">
    @endif
    @if ($showcase)
        <link rel="stylesheet" href="{{ asset('assets/css/auth-experience.css') }}?v={{ filemtime(public_path('assets/css/auth-experience.css')) }}">
    @endif
</head>

<body class="auth-body{{ $showcase ? ' auth-experience' : '' }} {{ $isRegister ? 'auth-register' : (request()->routeIs('password.*') ? 'auth-login auth-recovery' : 'auth-login') }}">
    @if ($showcase)
        <div class="auth-utility">
            <a class="auth-back-home" href="{{ route('home') }}"><span aria-hidden="true">←</span>&nbsp; 返回首页</a>
            @include('partials.theme-toggle')
        </div>
        @include('auth.partials.showcase', ['isRegister' => $isRegister])
    @else
        <div class="theme-fixed">@include('partials.theme-toggle')</div>
        <aside class="auth-side">
            <a class="brand" href="{{ route('home') }}">
                <img class="brand-logo" src="{{ asset('assets/images/devicloud-logo.png') }}" alt="Devicloud 德威云计算">
            </a>
            <div class="auth-copy">
                <h1>把复杂的云，<br>变成<span>简单的日常</span>。</h1>
                <p>从选购、支付到开通、运维，一个账户管理你的全部云资源。云机、裸金属、CDN，统一在控制台完成。</p>
                <div class="auth-points">
                    <div class="auth-point"><i>✓</i>真实资源、透明计费、可随时续费</div>
                    <div class="auth-point"><i>✓</i>快捷方便的下一代云交互模式</div>
                    <div class="auth-point"><i>✓</i>工单、公告与账单，一站式处理</div>
                </div>
            </div>
            <div class="small" style="position:relative;z-index:1;color:var(--dt-t-52647c,#52647c);">© {{ now()->year }} Deviy Cloud · 稳定、透明、可长期依赖</div>
        </aside>
    @endif

    <main class="auth-main">
        <div class="auth-card">
@yield('content')
        </div>
    </main>

    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}" defer></script>
    @if (request()->routeIs('password.*'))
        <script src="{{ asset('assets/js/password-recovery.js') }}?v={{ filemtime(public_path('assets/js/password-recovery.js')) }}" defer></script>
    @endif
</body>
</html>
