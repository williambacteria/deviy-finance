@extends('layouts.site')

@section('title', '帮助中心 · 德威云计算')
@section('description', '德威云计算帮助中心：云服务器购买开通、端口映射、远程连接与日常管理图文教程。')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/help-center.css') }}?v={{ filemtime(public_path('assets/css/help-center.css')) }}">
@endpush

@section('content')
    <section class="hc-hero">
        <div class="container hc-hero-inner">
            <div class="breadcrumb"><a href="{{ route('home') }}">首页</a> / 帮助中心</div>
            <div class="hc-eyebrow">DOCUMENTATION &amp; GUIDES</div>
            <h1>帮助中心</h1>
            <p>从选购开通到网络配置，用清晰的图文步骤带你完成每一次部署。找不到答案时，也可以随时联系人工支持。</p>
            <form class="hc-search" method="GET" action="{{ route('help') }}">
                <input class="input" type="search" name="q" value="{{ $search }}" placeholder="搜索教程，例如：端口映射、购买、RDP" aria-label="搜索帮助文档">
                <button class="btn btn-primary" type="submit"><x-icon name="search" :size="15" /> 搜索</button>
            </form>
            <div class="hc-hero-links">
                <span>常用：</span>
                @foreach ($categories->flatMap->articles->take(3) as $quickArticle)
                    <a href="{{ route('help.article', $quickArticle) }}">{{ $quickArticle->title }}</a>
                @endforeach
                <a href="{{ route('console.tickets.create') }}">提交工单</a>
            </div>
        </div>
    </section>

    <section class="hc-section">
        <div class="container hc-layout">
            <aside class="hc-side">
                <div class="hc-side-card">
                    <div class="hc-side-title"><x-icon name="layers" :size="14" /> 文档分类</div>
                    <nav class="hc-nav" aria-label="帮助文档分类">
                        <a href="{{ route('help') }}" @class(['active' => $search === ''])><x-icon name="file" :size="14" /> 全部文档 <small>{{ $categories->sum(fn ($category) => $category->articles->count()) }}</small></a>
                        @foreach ($categories as $category)
                            @if ($category->articles->isNotEmpty())
                                <a href="#hc-{{ $category->slug }}"><x-icon :name="$category->icon" :size="14" /> {{ $category->name }} <small>{{ $category->articles->count() }}</small></a>
                            @endif
                        @endforeach
                    </nav>
                </div>

                <div class="hc-side-card hc-support">
                    <div class="hc-side-title"><x-icon name="life-buoy" :size="14" /> 需要人工帮助</div>
                    <p>教程没有覆盖你的问题？提交工单，支持团队会在服务时间内跟进。</p>
                    <a class="btn btn-sm" href="{{ route('console.tickets.create') }}">提交工单</a>
                    <small>7×12 小时人工支持 · admin@deviy.cn</small>
                </div>
            </aside>

            <main class="hc-main">
                @if ($search !== '')
                    <div class="hc-main-head">
                        <div>
                            <h2>搜索结果</h2>
                            <p>关键词：{{ $search }}</p>
                        </div>
                        <span class="count">{{ $articles->total() }} 篇</span>
                    </div>

                    @if ($articles->isEmpty())
                        <div class="hc-empty">
                            <b>没有找到相关教程</b>
                            可以换一个关键词，或者提交工单让支持团队协助。
                        </div>
                    @else
                        <div class="hc-cards">
                            @foreach ($articles as $article)
                                @include('site.partials.help-card', ['article' => $article])
                            @endforeach
                        </div>
                        <div class="hc-pagination">{{ $articles->links('partials.pagination') }}</div>
                    @endif
                @else
                    <div class="hc-main-head">
                        <div>
                            <h2>全部教程</h2>
                            <p>按使用流程整理的实战文档，建议第一次使用先阅读「新手指南」。</p>
                        </div>
                        <span class="count">{{ $categories->sum(fn ($category) => $category->articles->count()) }} 篇文档</span>
                    </div>

                    @php($visibleCategories = $categories->filter(fn ($category) => $category->articles->isNotEmpty()))
                    @forelse ($visibleCategories as $category)
                        <section class="hc-group" id="hc-{{ $category->slug }}">
                            <div class="hc-group-head">
                                <x-icon :name="$category->icon" :size="17" />
                                <h3>{{ $category->name }}</h3>
                                <span>{{ $category->description }}</span>
                            </div>
                            <div class="hc-cards">
                                @foreach ($category->articles as $article)
                                    @include('site.partials.help-card', ['article' => $article])
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="hc-empty">
                            <b>帮助文档正在整理中</b>
                            请稍后回来查看，或通过工单联系我们。
                        </div>
                    @endforelse
                @endif
            </main>
        </div>
    </section>
@endsection
