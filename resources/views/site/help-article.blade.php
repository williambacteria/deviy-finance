@extends('layouts.site')

@section('title', $article->title.' · 帮助中心')
@section('description', $article->excerpt)

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/help-center.css') }}?v={{ filemtime(public_path('assets/css/help-center.css')) }}">
@endpush

@section('content')
    <section class="hc-article-hero">
        <div class="container">
            <div class="hc-breadcrumb">
                <a href="{{ route('home') }}">首页</a><span>/</span>
                <a href="{{ route('help') }}">帮助中心</a><span>/</span>
                <a href="{{ route('help') }}#hc-{{ $article->category?->slug }}">{{ $article->category?->name }}</a>
            </div>
            <div class="hc-eyebrow">{{ strtoupper($article->category?->slug ?? 'guide') }} · GUIDE</div>
            <h1 class="hc-article-title">{{ $article->title }}</h1>
            @if ($article->summary)
                <p class="hc-article-summary">{{ $article->summary }}</p>
            @endif
            <div class="hc-article-meta">
                <span><x-icon name="calendar" :size="13" /> {{ $article->published_at?->format('Y-m-d') }}</span>
                <span><x-icon name="clock" :size="13" /> 阅读约 {{ $article->reading_minutes }} 分钟</span>
                <span><x-icon name="eye" :size="13" /> {{ number_format($article->views) }} 次阅读</span>
                <span><x-icon name="layers" :size="13" /> {{ $article->category?->name }}</span>
            </div>
        </div>
    </section>

    <div class="container hc-article-layout">
        <aside class="hc-side">
            <div class="hc-side-card">
                <div class="hc-side-title"><x-icon name="layers" :size="14" /> {{ $article->category?->name }}</div>
                <nav class="hc-nav" aria-label="同类文档">
                    @foreach ($categories as $category)
                        @if ($category->articles->isNotEmpty())
                            <a href="{{ route('help') }}#hc-{{ $category->slug }}" @class(['active' => $category->id === $article->help_category_id])>
                                <x-icon :name="$category->icon" :size="14" /> {{ $category->name }}
                                <small>{{ $category->articles->count() }}</small>
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>
            <div class="hc-side-card hc-support">
                <div class="hc-side-title"><x-icon name="life-buoy" :size="14" /> 还需要帮助？</div>
                <p>按照教程操作后仍有问题，可以在控制台提交工单，并附上实例编号和报错信息。</p>
                <a class="btn btn-sm" href="{{ route('console.tickets.create') }}">提交工单</a>
                <small>7×12 小时人工支持 · admin@deviy.cn</small>
            </div>
        </aside>

        <article class="hc-article">
            @if ($article->cover_image && ! str_contains($article->body, '<img'))
                <img class="hc-cover" src="{{ asset($article->cover_image) }}" alt="{{ $article->title }}配图">
            @endif

            <div class="hc-prose">
                {!! $article->body !!}
            </div>

            <div class="hc-article-footer">
                @if ($previous)
                    <a href="{{ route('help.article', $previous) }}">
                        <small>上一篇</small>
                        <b>{{ $previous->title }}</b>
                    </a>
                @else
                    <span></span>
                @endif
                @if ($next)
                    <a class="next" href="{{ route('help.article', $next) }}">
                        <small>下一篇</small>
                        <b>{{ $next->title }}</b>
                    </a>
                @endif
            </div>
        </article>
    </div>
@endsection
