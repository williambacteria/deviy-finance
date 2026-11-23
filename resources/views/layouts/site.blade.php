<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ filemtime(public_path('assets/js/theme-init.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/fonts/noto-sc/noto-sc.css') }}?v=20261117">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '德威云计算') · {{ $siteSettings['site_slogan'] ?? '做最专业的云服务商' }}</title>
    <meta name="description" content="@yield('description', '德威云计算，专注为游戏云、企业云及中小型企业提供稳定、安全的计算与基础设施服务。')">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=20261124-brand1">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/deviy-site.css') }}?v={{ filemtime(public_path('assets/css/deviy-site.css')) }}">
    @stack('head')
    <link rel="stylesheet" href="{{ asset('assets/css/azure.css') }}?v={{ filemtime(public_path('assets/css/azure.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v={{ filemtime(public_path('assets/css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sky-site.css') }}?v={{ filemtime(public_path('assets/css/sky-site.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
</head>
<body class="site-body sky-site {{ request()->routeIs('home') ? 'sky-home' : 'sky-inner' }}">
    <header class="dv-header">
        <div class="dv-container dv-header-inner">
            <a class="dv-brand" href="{{ route('home') }}" aria-label="德威云计算">
                <img src="{{ asset('assets/images/devicloud-logo.png') }}?v=20261124-brand1" alt="德威云计算 · DEVIY CLOUD">
            </a>

            <nav class="dv-nav" id="dv-nav">
                <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">首页</a>
                <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">产品选购</a>
                <a href="{{ route('news.index') }}" class="{{ request()->routeIs('news.*') ? 'active' : '' }}">新闻公告</a>
                <a href="{{ route('help') }}" class="{{ request()->routeIs('help') ? 'active' : '' }}">帮助支持</a>
                <a href="{{ route('home') }}#about">关于我们</a>
                <a href="{{ route('home') }}#contact">联系我们</a>
                <span class="dv-mobile-auth">
                    @auth
                        <a href="{{ route('console.dashboard') }}">进入控制台</a>
                    @else
                        <a href="{{ route('login') }}">登录</a>
                        <a href="{{ route('register') }}">免费注册</a>
                    @endauth
                </span>
            </nav>

            <div class="dv-actions">
                @auth
                    <a class="dv-btn dv-btn-ghost" href="{{ route('console.dashboard') }}">控制台</a>
                @else
                    <a class="dv-btn dv-btn-ghost" href="{{ route('login') }}">登录</a>
                    <a class="dv-btn dv-btn-primary" href="{{ route('register') }}">免费注册</a>
                @endauth
            </div>

            @include('partials.theme-toggle')
            <button class="dv-mobile-toggle" type="button" aria-label="打开菜单" aria-controls="dv-nav" aria-expanded="false" data-mobile-toggle>
                <x-icon name="menu" :size="23" />
            </button>
        </div>
    </header>

    @include('partials.flash')

    @yield('content')

    <footer class="dv-site-footer">
        <div class="dv-container">
            <div class="dv-footer-grid">
                <div class="dv-footer-brand">
                    <img class="dv-footer-logo" src="{{ asset('assets/images/devicloud-logo.png') }}?v=20261124-brand1" alt="德威云计算 · DEVIY CLOUD">
                    <p>德威云计算（Devicloud）成立于 2014 年，专注为游戏云、政务云及中小企业提供稳定、透明、可长期依赖的云计算基础设施。我们整合 AMD、Intel 多平台算力，提供云服务器、NAT 云主机、裸金属服务器与游戏云等产品，覆盖香港、苏州、东京、美国等多个区域；通过中文控制台将选购、账单、监控、端口映射与工单支持连成一体，并以 7×12 小时人工服务回应每一次需求。把复杂留给云，把从容留给你——从一个想法，到云上万千可能。</p>
                </div>
                <div class="dv-footer-col">
                    <h4>快速入口</h4>
                    <a href="{{ route('login') }}">登录用户</a>
                    <a href="{{ route('register') }}">注册用户</a>
                    <a href="{{ route('help') }}">帮助中心</a>
                    <a href="{{ route('news.index') }}">新闻公告</a>
                </div>
                <div class="dv-footer-col">
                    <h4>联系我们</h4>
                    <a href="mailto:{{ $siteSettings['support_email'] ?? 'admin@deviy.cn' }}">{{ $siteSettings['support_email'] ?? 'admin@deviy.cn' }}</a>
                    <a href="{{ $siteSettings['support_qq_url'] ?? 'https://qm.qq.com/cgi-bin/qm/qr?k=854124946' }}" target="_blank" rel="noopener noreferrer">QQ 在线咨询 · 添加好友 ↗</a>
                    <span>7 × 12 小时人工支持</span>
                    <span>Copyright 2014–{{ now()->year }}</span>
                </div>
            </div>
            <div class="dv-footer-bottom">
                <span>© 2014–{{ now()->year }} 德威云计算 All Rights Reserved.</span>
                <nav class="site-legal-links" aria-label="网站协议与备案"><a href="{{ route('terms') }}">服务条款</a><a href="{{ route('privacy') }}">隐私政策</a><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer">{{ $siteSettings['icp_number'] ?? '蜀ICP备2024089086号' }}</a></nav>
            </div>
        </div>
    </footer>

    <script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
    <script src="{{ asset('assets/js/sky-site.js') }}?v={{ filemtime(public_path('assets/js/sky-site.js')) }}" defer></script>
    @stack('scripts')
    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}" defer></script>
</body>
</html>
