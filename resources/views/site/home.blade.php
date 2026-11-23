@extends('layouts.site')

@section('title', '德威云计算 · 让业务拥有更好的云端起点')
@section('description', '德威云计算提供云服务器、NAT 云主机与游戏云服务。清晰的产品配置、灵活的计费方式与中文控制台，让云资源管理更从容。')

@section('content')
    <section class="sky-hero">
        <div class="dv-container sky-hero-grid">
            <div class="sky-hero-copy">
                <div class="sky-eyebrow">IDEAS BEGIN HERE. CLOUD TAKES THEM FURTHER.</div>
                <h1>从一个想法，<br><span>到云上万千可能。</span></h1>
                <p>云服务器、NAT 云主机与游戏云，一站式连接你的业务。<br>让每一次部署，都更清晰、更从容。</p>
                <div class="sky-hero-actions">
                    <a class="dv-btn dv-btn-primary" href="{{ route('products.index') }}">选购云服务器 <x-icon name="arrow-right" :size="16" /></a>
                    <a class="dv-btn dv-btn-ghost" href="#platform">了解云端体验 <x-icon name="arrow-right" :size="16" /></a>
                </div>
                <div class="sky-hero-trust"><span><x-icon name="check" :size="13" /> 透明计费</span><span><x-icon name="check" :size="13" /> 中文控制台</span><span><x-icon name="check" :size="13" /> 工单支持</span></div>
            </div>
            <div class="sky-hero-visual">
                <div class="motion-art" data-motion="datacenter"></div>
                <div class="sky-art-label sky-art-label-top"><x-icon name="cloud" :size="22" /><div><b>CORE COMPUTE</b><small>连接算力与每一次创造</small></div></div>
                <div class="sky-art-label sky-art-label-bottom"><x-icon name="layers" :size="22" /><div><b>云端基础设施</b><small>计算 · 网络 · 资源编排</small></div></div>
            </div>
        </div>
    </section>

    <section class="sky-section" id="products">
        <div class="dv-container">
            <div class="sky-section-head"><div><div class="sky-eyebrow">CLOUD FOR YOUR NEXT CHAPTER</div><h2>为每一种业务，找到合适的云。</h2><p>配置与价格清晰可见，选择适合自己的资源规格。</p></div><a class="sky-text-link" href="{{ route('products.index') }}">查看全部产品 <x-icon name="arrow-right" :size="15" /></a></div>
            <div class="sky-products-toolbar"><span class="sky-products-title"><x-icon name="package" :size="16" /> 精选云资源</span><span>云服务器 · NAT 云主机 · 多种业务场景</span></div>
            @include('site.partials.homepage-featured', ['featuredProducts' => $featuredProducts, 'featuredMeta' => $featuredMeta])
        </div>
    </section>

    <section class="sky-proof-section" aria-label="云服务优势"><div class="dv-container"><p class="sky-proof-heading">围绕你的业务，做好每一项基础服务。</p><div class="sky-proof-grid">
        <article class="sky-proof-card"><x-icon name="cpu" :size="22" /><div><b>多种算力</b><small>AMD & Intel 平台</small></div></article>
        <article class="sky-proof-card"><x-icon name="globe" :size="22" /><div><b>多区域选择</b><small>按业务需求选择部署区域</small></div></article>
        <article class="sky-proof-card"><x-icon name="wallet" :size="22" /><div><b>灵活计费</b><small>支持多种计费周期</small></div></article>
        <article class="sky-proof-card"><x-icon name="layers" :size="22" /><div><b>一站式体验</b><small>选购 · 账单 · 运维</small></div></article>
    </div></div></section>

    <section class="sky-section sky-soft-section" id="platform">
        <div class="dv-container">
            <div class="sky-section-head"><div><div class="sky-eyebrow">YOUR CLOUD, UNDER CONTROL</div><h2>云端体验，从容一点。</h2></div><a class="sky-text-link" href="{{ route('console.dashboard') }}">进入控制台 <x-icon name="arrow-right" :size="15" /></a></div>
            <div class="sky-platform-grid">
                <div class="sky-platform-story"><h3>每一份资源，<br>都有清晰的掌控。</h3><p>从服务状态到支持工单，让日常管理始终井然有序。</p><div class="motion-art" data-motion="monitor"></div></div>
                <div class="sky-capabilities">
                    <article><x-icon name="server" :size="26" /><h3>资源清晰可见</h3><p>实例、配置与运行状态，集中在一个工作空间。</p></article>
                    <article><x-icon name="chart" :size="26" /><h3>掌握运行动态</h3><p>查看资源监控，让日常运维更有方向。</p></article>
                    <article><x-icon name="wallet" :size="26" /><h3>每笔支出有迹可循</h3><p>订单、账单与账户余额，清晰呈现。</p></article>
                    <article><x-icon name="ticket" :size="26" /><h3>支持就在身边</h3><p>使用问题与服务咨询，通过工单联系支持团队。</p></article>
                </div>
            </div>
        </div>
    </section>

    <section class="sky-section" id="about">
        <div class="dv-container sky-about-grid">
            <div class="sky-about-art"><div class="motion-art" data-motion="shield"></div><span>DEDICATED TO YOUR NEXT STEP.</span></div>
            <div class="sky-about-copy"><div class="sky-eyebrow">ABOUT DEVIY CLOUD</div><h2>把复杂留给云，<br>把从容留给你。</h2><p>德威云计算专注于计算与基础设施服务。从游戏云、企业应用到个人创作，我们希望让云资源的选购、管理与支持变得清晰、简单。</p><ul><li><x-icon name="check" :size="15" /> 多种云资源与部署区域，面向不同业务需求</li><li><x-icon name="check" :size="15" /> 中文控制台，连接选购、账单与日常管理</li><li><x-icon name="check" :size="15" /> 7×12 小时人工支持，帮助解决使用问题</li></ul><a class="sky-text-link" href="{{ route('help') }}">了解服务支持 <x-icon name="arrow-right" :size="15" /></a></div>
        </div>
    </section>

    @include('site.partials.homepage-testimonials', ['testimonials' => $testimonials])

    <section class="sky-section sky-soft-section" id="news">
        <div class="dv-container"><div class="sky-section-head"><div><div class="sky-eyebrow">WHAT'S NEW</div><h2>云上动态，及时了解。</h2><p>节点维护、产品更新与站点公告。</p></div><a class="sky-text-link" href="{{ route('news.index') }}">全部新闻公告 <x-icon name="arrow-right" :size="15" /></a></div><div class="sky-news-grid">
            @forelse ($announcements as $announcement)
                <a class="sky-news-card" href="{{ route('news.show', $announcement) }}"><div><span>{{ $announcement->type === 'news' ? '站点新闻' : '公告' }}</span><time>{{ $announcement->published_at?->format('Y.m.d') }}</time></div><h3>{{ $announcement->title }}</h3><p>{{ $announcement->summary }}</p><span class="sky-news-link">阅读全文 <x-icon name="arrow-right" :size="14" /></span></a>
            @empty
                <div class="sky-news-empty"><x-icon name="megaphone" :size="25" /><h3>新的动态，即将到来。</h3><p>后续产品更新与站点公告将在这里发布。</p><a class="sky-text-link" href="{{ route('help') }}">先去帮助中心看看 →</a></div>
            @endforelse
        </div></div>
    </section>

    <section class="sky-section" id="contact">
        <div class="dv-container"><div class="sky-contact"><div class="sky-contact-copy"><div class="sky-eyebrow">LET'S BUILD SOMETHING.</div><h2>让下一次创造，从这里开始。</h2><p>售前咨询、技术支持与商务合作，欢迎随时联系。</p><div class="sky-contact-actions"><a class="dv-btn dv-btn-primary" href="{{ route('products.index') }}">选择我的云服务器 <x-icon name="arrow-right" :size="15" /></a><a class="dv-btn dv-btn-ghost" href="{{ route('console.tickets.create') }}">提交工单</a></div></div><div class="sky-contact-details"><a href="mailto:admin@deviy.cn"><x-icon name="mail" :size="18" /><span><small>支持邮箱</small><b>admin@deviy.cn</b></span><span class="sky-contact-arrow">↗</span></a><a href="https://qm.qq.com/cgi-bin/qm/qr?k=854124946" target="_blank" rel="noopener noreferrer" aria-label="QQ 在线咨询，添加好友"><x-icon name="users" :size="18" /><span><small>在线咨询 · 添加好友</small><b>通过 QQ 联系我们</b></span><span class="sky-contact-arrow">↗</span></a><div><x-icon name="clock" :size="18" /><span><small>服务时间</small><b>7×12 小时人工支持</b></span></div></div></div></div>
    </section>
    @include('site.partials.homepage-friend-links', ['friendLinks' => $friendLinks])

    <button class="site-back-to-top" type="button" aria-label="返回顶部" title="返回顶部" data-back-to-top hidden><span class="site-back-to-top-icon" aria-hidden="true"><x-icon name="arrow-right" :size="21" /></span></button>
@endsection

@push('head')
    @if ($friendLinks !== [])
        <link rel="stylesheet" href="{{ asset('assets/css/friend-links.css') }}?v={{ filemtime(public_path('assets/css/friend-links.css')) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/css/back-to-top.css') }}?v={{ filemtime(public_path('assets/css/back-to-top.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/testimonials.css') }}?v={{ filemtime(public_path('assets/css/testimonials.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/back-to-top.js') }}?v={{ filemtime(public_path('assets/js/back-to-top.js')) }}" defer></script>
    <script src="{{ asset('assets/js/testimonials.js') }}?v={{ filemtime(public_path('assets/js/testimonials.js')) }}" defer></script>
    <script src="{{ asset('assets/js/site-motions.js') }}?v={{ filemtime(public_path('assets/js/site-motions.js')) }}" defer></script>
@endpush
