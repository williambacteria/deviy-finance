
    <div class="page-head"><div><h1>首页用户评价</h1><p>独立的后台内容管理模块，仅管理员可录入与编辑；官网只展示，不开放用户提交。</p></div><div class="page-actions"><a class="btn btn-ghost" href="{{ route('home') }}#testimonials" target="_blank" rel="noopener">查看首页效果 ↗</a></div></div>
    @if ($errors->any())<div class="note danger" role="alert" style="margin-bottom:18px">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.homepage-testimonials.update') }}" data-testimonials-editor novalidate enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="panel review-manager"><div class="panel-head"><h2>评价管理</h2><span class="meta" data-review-count></span></div><div class="panel-body"><p class="testimonial-editor-intro">搜索昵称、使用场景或评价正文，点击评价展开编辑。所有修改统一保存，列表筛选不影响保存内容与首页展示顺序。</p>
            <div class="review-search-toolbar" role="search"><label class="review-search"><x-icon name="search" :size="18" /><input type="search" data-review-search placeholder="搜索昵称、场景、评价内容" aria-label="搜索用户评价"></label><select class="select" data-review-visibility aria-label="展示状态"><option value="">全部状态</option><option value="enabled">首页展示中</option><option value="disabled">未展示</option></select><select class="select" data-review-rating aria-label="评价评分"><option value="">全部评分</option>@foreach ([5, 4, 3, 2, 1] as $score)<option value="{{ $score }}">{{ $score }} 星</option>@endforeach</select><button type="button" class="btn btn-ghost btn-sm" data-review-reset>重置</button></div>
            <div class="review-list-caption"><span data-review-results role="status" aria-live="polite"></span><label>每页 <select data-review-pagesize aria-label="每页评价数"><option value="10">10 条</option><option value="20">20 条</option><option value="30">全部</option></select></label></div>
            <div class="testimonial-editor-list" data-editor-list>
                @foreach (old('items', $items) as $key => $item)
                    @include('admin.homepage-testimonials.row', ['key' => $key, 'item' => $item])
                @endforeach
            </div>
            <div class="testimonial-editor-empty" data-editor-empty><x-icon name="users" :size="25" /><h3>还没有录入用户评价</h3><p>由管理员添加并启用后，首页会自动展示。两条及以上评价支持循环滚动。</p></div>
            <div class="review-no-results" data-review-no-results hidden><x-icon name="search" :size="25" /><b>没有找到匹配的评价</b><p>换个关键词，或重置筛选试试。</p></div>
            <nav class="review-pagination" aria-label="评价分页" data-review-pagination><button class="btn btn-ghost btn-sm" type="button" data-review-prev>上一页</button><span data-review-page></span><button class="btn btn-ghost btn-sm" type="button" data-review-next>下一页</button></nav>
            <button class="btn btn-soft" type="button" data-add-testimonial><x-icon name="plus" :size="16" /> 添加用户评价</button>
        </div><div class="panel-footer review-save-bar"><span class="muted small">添加、移除、排序或上下架后，请点击保存。</span><button class="btn btn-primary" type="submit">保存全部评价</button><span class="review-draft-badge" data-review-draft hidden>有未保存的修改</span></div></div>
        <template data-review-template>@include('admin.homepage-testimonials.row', ['key' => '__INDEX__', 'item' => []])</template>
        <p class="testimonial-editor-status" role="status" aria-live="polite" data-editor-status></p>
    </form>


@push('scripts')
    <script src="{{ asset('assets/js/testimonials-admin.js') }}?v={{ filemtime(public_path('assets/js/testimonials-admin.js')) }}" defer></script>
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/reviews-manager.css') }}?v={{ filemtime(public_path('assets/css/reviews-manager.css')) }}">
@endpush
