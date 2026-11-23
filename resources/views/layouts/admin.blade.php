<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ filemtime(public_path('assets/js/theme-init.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/fonts/noto-sc/noto-sc.css') }}?v=20261117">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '运营后台') · Deviy Cloud</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    @stack('styles')
    @stack('head')

    <link rel="stylesheet" href="{{ asset('assets/css/azure.css') }}?v={{ filemtime(public_path('assets/css/azure.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/identity-workspace.css') }}?v={{ filemtime(public_path('assets/css/identity-workspace.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v={{ filemtime(public_path('assets/css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-organization.css') }}?v={{ filemtime(public_path('assets/css/admin-organization.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site-refinement.css') }}?v={{ filemtime(public_path('assets/css/site-refinement.css')) }}">
</head>
<body class="app-body azure-admin">
    <div class="app-shell">
        <aside class="sidebar" id="console-sidebar">
            <button class="console-mobile-nav-close" type="button" aria-label="关闭后台菜单" data-console-nav-close>×</button>
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <img class="brand-logo" src="{{ asset('assets/images/devicloud-logo.png') }}" alt="Devicloud 德威云计算">
            </a>
            <div class="azure-workspace azure-workspace--compact azure-workspace--admin"><span class="azure-workspace-icon"><x-icon name="building" :size="16" /></span><div><b>运营后台</b><small>平台管理</small></div></div>

            @include('admin.sidebar')

            <div class="side-user">
                <div class="avatar">{{ mb_substr(auth('admin')->user()->name, 0, 1) }}</div>
                <div>
                    <b>{{ auth('admin')->user()->name }}</b>
                    <small>{{ ['super_admin' => '超级管理员', 'finance' => '财务', 'support' => '客服'][auth('admin')->user()->role] ?? auth('admin')->user()->role }}</small>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="icon-btn" type="submit" title="退出登录"><x-icon name="logout" :size="16" /></button>
                </form>
            </div>
        </aside>

        <div class="console-nav-backdrop" data-console-nav-close></div>
        <div class="main">
            <header class="topbar">
                <button class="console-mobile-nav-toggle" type="button" aria-label="打开后台菜单" aria-controls="console-sidebar" aria-expanded="false" data-console-nav-toggle><x-icon name="menu" :size="21" /></button>
                <div class="crumb">运营后台 / <b>@yield('page-title', '概览')</b></div>
                <div class="search">
                    <x-icon name="search" :size="15" />
                    <input class="input" type="search" placeholder="搜索客户、订单、实例…" aria-label="搜索">
                </div>
                <div class="top-actions">
                    @include('partials.theme-toggle')
                    <a class="btn btn-ghost btn-sm" href="{{ route('home') }}" target="_blank"><x-icon name="external" :size="14" /> 查看官网</a>
                    <span class="online-dot">系统正常</span>
                </div>
            </header>

            <main class="page">
                @include('partials.flash')
                @yield('content')
            <footer class="azure-page-footer"><span>© {{ now()->year }} Deviy Cloud · 德威云计算</span><span>云资源 · 财务 · 服务支持</span></footer>

            </main>
        </div>
    </div>
    <script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
    @stack('scripts')
    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}" defer></script>
</body>
</html>
