@extends('layouts.site')

@section('title', $announcement->title)

@section('content')
    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb"><a href="{{ route('home') }}">首页</a> / <a href="{{ route('news.index') }}">新闻公告</a> / 正文</div>
            <div class="small" style="color:var(--dt-t-38bdf8,#38bdf8);font-weight:700;letter-spacing:1px;margin-bottom:12px">{{ $announcement->type === 'news' ? '站点新闻' : '公告' }} · {{ $announcement->published_at?->format('Y-m-d H:i') }}</div>
            <h1 style="font-size:38px;max-width:900px">{{ $announcement->title }}</h1>
            <p>{{ $announcement->summary }}</p>
        </div>
    </section>

    <section class="section" style="padding-top:18px">
        <div class="container" style="max-width:880px">
            <article class="light-panel" style="font-size:14px;line-height:1.95;color:var(--dt-t-334155,#334155);white-space:pre-line">{{ $announcement->body }}</article>
            <div class="mt-4"><a class="btn btn-ghost" href="{{ route('news.index') }}">← 返回列表</a></div>
        </div>
    </section>
@endsection
