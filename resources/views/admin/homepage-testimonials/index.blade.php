@extends('layouts.admin')
@section('title', '首页用户评价')
@section('page-title', '首页用户评价')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/testimonials.css') }}?v={{ filemtime(public_path('assets/css/testimonials.css')) }}">
@endpush

@section('content')
    <div class="page-head"><div><h1>首页用户评价</h1><p>独立的后台内容管理模块，仅管理员可录入与编辑；官网只展示，不开放用户提交。</p></div><div class="page-actions"><a class="btn btn-ghost" href="{{ route('home') }}#testimonials" target="_blank" rel="noopener">查看首页效果 ↗</a></div></div>
    @if ($errors->any())<div class="note danger" role="alert" style="margin-bottom:18px">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.homepage-testimonials.update') }}" data-testimonials-editor enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="panel"><div class="panel-head"><h2>评价内容</h2><span class="meta" data-review-count></span></div><div class="panel-body"><p class="testimonial-editor-intro">最多保存 30 条；前台按从上到下的顺序展示已启用的评价。未启用的记录仅保留在后台。请确认评价及用户昵称已获得适当的公开授权。</p>
            <div class="testimonial-editor-list" data-editor-list>
                @foreach (old('items', $items) as $key => $item)
                    @include('admin.homepage-testimonials.row', ['key' => $key, 'item' => $item])
                @endforeach
            </div>
            <div class="testimonial-editor-empty" data-editor-empty><x-icon name="users" :size="25" /><h3>还没有录入用户评价</h3><p>由管理员添加并启用后，首页会自动展示。两条及以上评价支持循环滚动。</p></div>
            <button class="btn btn-soft" type="button" data-add-testimonial><x-icon name="plus" :size="16" /> 添加用户评价</button>
        </div><div class="panel-footer"><span class="muted small">添加、移除、排序或上下架后，请点击保存。</span><button class="btn btn-primary" type="submit">保存全部评价</button></div></div>
        <template data-review-template>@include('admin.homepage-testimonials.row', ['key' => '__INDEX__', 'item' => []])</template>
        <p class="testimonial-editor-status" role="status" aria-live="polite" data-editor-status></p>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/testimonials-admin.js') }}?v={{ filemtime(public_path('assets/js/testimonials-admin.js')) }}" defer></script>
@endpush
