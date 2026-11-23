<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ filemtime(public_path('assets/js/theme-init.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/fonts/noto-sc/noto-sc.css') }}?v=20261117">

    <title>管理员登录 · Deviy Cloud</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=20261124-brand1">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/azure.css') }}?v={{ filemtime(public_path('assets/css/azure.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v={{ filemtime(public_path('assets/css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
</head>

<body class="auth-body">
    <div class="theme-fixed">@include('partials.theme-toggle')</div>
    <aside class="auth-side">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo" src="{{ asset('assets/images/devicloud-logo.png') }}?v=20261124-brand1" alt="德威云计算 · DEVIY CLOUD">
        </a>
        <div class="auth-copy">
            <h1>运营后台，<br><span>只保留必要的事</span>。</h1>
            <p>客户、业务、财务、工单、内容与节点，在一个工作台完成。所有关键操作留痕，资金变动可追溯。</p>
            <div class="auth-points">
                <div class="auth-point"><i>✓</i>客户 / 业务 / 订单 / 工单统一检索</div>
                <div class="auth-point"><i>✓</i>节点状态、带宽与 NAT 映射可见</div>
                <div class="auth-point"><i>✓</i>调账、停复机、退款全量审计</div>
            </div>
        </div>
        <div class="small" style="position:relative;z-index:1;color:var(--dt-t-52647c,#52647c);">Deviy Cloud Operations · 内部系统</div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <a class="brand" href="{{ route('home') }}">
                <img class="brand-logo" src="{{ asset('assets/images/devicloud-logo.png') }}?v=20261124-brand1" alt="德威云计算 · DEVIY CLOUD">
            </a>

            @include('partials.flash')

            <h2>管理员登录</h2>
            <p class="lead">仅限内部运营人员访问。</p>

            <form method="POST" action="{{ route('admin.login.store') }}">
                @csrf
                <div class="form-field">
                    <label class="form-label" for="email">管理员账号</label>
                    <input class="input" id="email" name="email" type="text" value="{{ old('email') }}" placeholder="william" required autofocus>
                </div>
                <div class="form-field mt-4">
                    <label class="form-label" for="password">密码</label>
                    <input class="input" id="password" name="password" type="password" placeholder="请输入密码" required>
                </div>
                <button class="btn btn-primary btn-block mt-6" type="submit">进入运营后台</button>
            </form>
        </div>
    </main>
    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}" defer></script>
</body>
</html>
