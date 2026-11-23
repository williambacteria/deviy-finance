<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ filemtime(public_path('assets/js/theme-init.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/fonts/noto-sc/noto-sc.css') }}?v=20261117">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '控制台') · Deviy Cloud</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    @stack('styles')
    @stack('head')

    <link rel="stylesheet" href="{{ asset('assets/css/azure.css') }}?v={{ filemtime(public_path('assets/css/azure.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/identity-workspace.css') }}?v={{ filemtime(public_path('assets/css/identity-workspace.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v={{ filemtime(public_path('assets/css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site-refinement.css') }}?v={{ filemtime(public_path('assets/css/site-refinement.css')) }}">
</head>
<body class="app-body azure-console">
    @php
        $unreadNoticeCount = auth()->user()->notices()->whereNull('read_at')->count();
    @endphp
    <div class="app-shell">
        <aside class="sidebar" id="console-sidebar">
            <button class="console-mobile-nav-close" type="button" aria-label="关闭控制台菜单" data-console-nav-close>×</button>
            <a class="brand" href="{{ route('console.dashboard') }}">
                <img class="brand-logo" src="{{ asset('assets/images/devicloud-logo.png') }}" alt="Devicloud 德威云计算">
            </a>
            <div class="azure-workspace azure-workspace--compact azure-workspace--user"><span class="azure-workspace-icon"><x-icon name="users" :size="16" /></span><div><b>用户控制台</b><small>客户工作区</small></div></div>

            <div class="side-label">工作台</div>
            <nav class="side-nav">
                <a class="side-link {{ request()->routeIs('console.dashboard') ? 'active' : '' }}" href="{{ route('console.dashboard') }}"><x-icon name="dashboard" /> 概览</a>
                <a class="side-link {{ request()->routeIs('console.services.*') ? 'active' : '' }}" href="{{ route('console.services.index') }}"><x-icon name="server" /> 我的资源</a>
                <a class="side-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}"><x-icon name="package" /> 购买资源</a>
            </nav>

            <div class="side-label">财务</div>
            <nav class="side-nav">
                <a class="side-link {{ request()->routeIs('console.orders.*') ? 'active' : '' }}" href="{{ route('console.orders.index') }}"><x-icon name="receipt" /> 订单</a>
                <a class="side-link {{ request()->routeIs('console.recharge.*') ? 'active' : '' }}" href="{{ route('console.recharge.index') }}"><x-icon name="wallet" /> 账户充值</a>
                <a class="side-link {{ request()->routeIs('console.invoices.*') ? 'active' : '' }}" href="{{ route('console.invoices.index') }}"><x-icon name="file" /> 账单与发票</a>
            </nav>

            <nav class="side-nav"><a class="side-link {{ request()->routeIs('console.reseller.*') ? 'active' : '' }}" href="{{ route('console.reseller.index') }}"><x-icon name="plug" /> 代理与 API</a></nav>
            <div class="side-label">支持</div>
            <nav class="side-nav">
                <a class="side-link {{ request()->routeIs('console.notices.*') ? 'active' : '' }}" href="{{ route('console.notices.index') }}"><x-icon name="bell" /> 消息通知 @if($unreadNoticeCount > 0)<span class="side-badge">{{ $unreadNoticeCount > 99 ? '99+' : $unreadNoticeCount }}</span>@endif</a>
                <a class="side-link {{ request()->routeIs('console.tickets.*') ? 'active' : '' }}" href="{{ route('console.tickets.index') }}"><x-icon name="ticket" /> 工单</a>
                <a class="side-link {{ request()->routeIs('console.domain-allowlist.*') ? 'active' : '' }}" href="{{ route('console.domain-allowlist.index') }}"><x-icon name="globe" /> 域名过白</a>
                <a class="side-link {{ request()->routeIs('console.real-name*') ? 'active' : '' }}" href="{{ route('console.real-name') }}"><x-icon name="shield" /> 实名认证</a>
                <a class="side-link {{ request()->routeIs('console.settings*') ? 'active' : '' }}" href="{{ route('console.settings') }}"><x-icon name="settings" /> 账户设置</a>
                <a class="side-link" href="{{ route('help') }}"><x-icon name="life-buoy" /> 帮助中心</a>
            </nav>

            <div class="side-user">
                <div class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                <div>
                    <b>{{ auth()->user()->name }}</b>
                    <small>{{ auth()->user()->customer_group === 'vip' ? 'VIP 客户' : '标准客户' }}</small>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="icon-btn" type="submit" title="退出登录"><x-icon name="logout" :size="16" /></button>
                </form>
            </div>
        </aside>

        <div class="console-nav-backdrop" data-console-nav-close></div>

        <div class="main">
            <header class="topbar">
                <button class="console-mobile-nav-toggle" type="button" aria-label="打开控制台菜单" aria-controls="console-sidebar" aria-expanded="false" data-console-nav-toggle>
                    <x-icon name="menu" :size="21" />
                </button>
                <div class="crumb">控制台 / <b>@yield('page-title', '概览')</b></div>
                <div class="top-actions">
                    @include('partials.theme-toggle')
                    <span class="online-dot">服务正常</span>
                    <a class="azure-top-link" href="{{ route('console.notices.index') }}" aria-label="消息通知"><x-icon name="bell" :size="18" />@if ($unreadNoticeCount > 0)<span class="azure-unread"></span>@endif</a>
                </div>
            </header>

            @php
                $showRealNamePrompt = auth()->check()
                    && in_array(auth()->user()->real_name_status, ['unverified', 'rejected'], true)
                    && ! session('real_name_prompt_shown');
                if ($showRealNamePrompt) {
                    session()->put('real_name_prompt_shown', true);
                }
            @endphp
            @if ($showRealNamePrompt)
                <div class="modal open real-name-prompt" id="real-name-prompt" aria-hidden="false">
                    <div class="modal-card">
                        <div class="modal-head"><h2>{{ auth()->user()->real_name_status === 'rejected' ? '实名认证未通过' : '完成实名认证' }}</h2><button class="modal-close" type="button" data-real-name-prompt-close>×</button></div>
                        <div class="modal-body">
                            <p style="margin:0 0 10px;color:var(--dt-t-334155,#334155)">购买云服务器前需要完成实名认证。</p>
                            <p class="small muted" style="margin:0 0 20px">{{ auth()->user()->real_name_status === 'rejected' ? '你的实名资料未通过审核，请修改后重新提交。' : '未实名账号暂时无法购买商品，完成认证后即可正常下单。' }}</p>
                            <div class="flex items-center gap-3">
                                <a class="btn btn-primary" href="{{ route('console.real-name') }}">去实名认证</a>
                                <button class="btn btn-ghost" type="button" data-real-name-prompt-close>稍后再说</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script src="{{ asset('assets/js/real-name-prompt.js') }}" defer></script>
            @endif

            @if (session('impersonator_admin_id'))
                <div class="impersonate-bar">
                    <span>正在以客户身份查看：<b>{{ auth()->user()?->name }}</b><span class="impersonate-muted">（管理员 {{ session('impersonator_admin_name') }}）</span></span>
                    <form method="POST" action="{{ route('impersonate.leave') }}">
                        @csrf
                        <button class="btn btn-sm" type="submit">返回管理员</button>
                    </form>
                </div>
            @endif

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
