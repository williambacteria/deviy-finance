@extends('layouts.site')

@section('title', '新闻公告')

@section('content')
    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb">首页 / 新闻公告</div>
            <h1>新闻与公告</h1>
            <p>节点维护、产品更新与站点动态。重要变更会同步发送到控制台消息中心。</p>
        </div>
    </section>

    <section class="section" style="padding-top:18px">
        <div class="container">
            @if ($announcements->count())
                <div class="grid-3">
                    @foreach ($announcements as $announcement)
                        <a class="article-card" href="{{ route('news.show', $announcement) }}" style="display:block">
                            <div class="small" style="color:var(--dt-t-2563eb,#2563eb);font-weight:700;margin-bottom:7px">{{ $announcement->type === 'news' ? '站点新闻' : '公告' }} · {{ $announcement->published_at?->format('Y-m-d') }}</div>
                            <h3>{{ $announcement->title }}</h3>
                            <p>{{ $announcement->summary }}</p>
                            <span class="small" style="color:var(--dt-t-2563eb,#2563eb);display:inline-block;margin-top:12px">阅读全文 →</span>
                        </a>
                    @endforeach
                </div>
                <div class="mt-6">{{ $announcements->links('partials.pagination') }}</div>
            @else
                <div class="light-panel empty-state">
                    <div class="empty-icon"><x-icon name="megaphone" :size="22" /></div>
                    <h3>暂无公告</h3>
                    <p>有新的维护或产品动态时会显示在这里。</p>
                </div>
            @endif
        </div>
    </section>
@endsection
