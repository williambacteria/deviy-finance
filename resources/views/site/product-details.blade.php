@extends('layouts.site')

@section('title', $product->name.' · 商品详情')

@section('content')
    @php($details = is_array($product->metadata['details'] ?? null) ? $product->metadata['details'] : [])
    @php($detailContent = trim((string) ($product->metadata['detail_page_content'] ?? '')))

    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb"><a href="{{ route('home') }}">首页</a> / <a href="{{ route('products.index') }}">产品选购</a> / <a href="{{ route('products.show', $product) }}">{{ $product->name }}</a> / 商品详情</div>
            <h1>{{ $product->name }}</h1>
            <p>{{ $product->short_description }}</p>
        </div>
    </section>

    <section class="section" style="padding-top:18px">
        <div class="container">
            <div class="product-detail-page">
                <div class="product-detail-page-head">
                    <div>
                        <div class="small muted">{{ $product->group?->name }} · {{ $product->region }}</div>
                        <h2>{{ $product->name }} 商品详情</h2>
                    </div>
                    <div class="flex gap-2" style="flex-wrap:wrap">
                        <a class="btn btn-ghost" href="{{ route('products.show', $product) }}">返回购买页</a>
                        @if ($product->isPurchasable())
                            <a class="btn btn-primary" href="{{ route('products.show', $product) }}">立即购买</a>
                        @endif
                    </div>
                </div>

                @if ($product->description)
                    <div class="product-detail-content">
                        {!! nl2br(e($product->description)) !!}
                    </div>
                @endif

                @if ($details !== [])
                    <div class="product-detail-section">
                        <h3>商品参数</h3>
                        <dl class="product-detail-specs">
                            @foreach ($details as $detail)
                                <div><dt>{{ $detail['label'] ?? '' }}</dt><dd>{{ $detail['value'] ?? '' }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                @if ($detailContent !== '')
                    <div class="product-detail-section">
                        <h3>详情说明</h3>
                        <div class="product-detail-content">{!! nl2br(e($detailContent)) !!}</div>
                    </div>
                @endif

                @if ($details === [] && $detailContent === '')
                    <div class="note info">该商品暂未补充更多详情，购买前可先咨询客服。</div>
                @endif
            </div>
        </div>
    </section>
@endsection
