@pushOnce('styles','product-copy-picker-style')
<link rel="stylesheet" href="{{ asset('assets/css/product-copy-picker.css') }}?v={{ filemtime(public_path('assets/css/product-copy-picker.css')) }}">
@endPushOnce
@pushOnce('scripts','product-copy-picker-script')
<script src="{{ asset('assets/js/product-copy-picker.js') }}?v={{ filemtime(public_path('assets/js/product-copy-picker.js')) }}" defer></script>
@endPushOnce
<details class="copy-product-menu copy-product-grouped" data-copy-picker>
    <summary class="btn btn-ghost"><x-icon name="copy" :size="15" /> 复制商品</summary>
    <div class="copy-product-panel" role="region" aria-label="按分组选择复制商品">
        <header class="cp-heading"><div><b>选择要复制的商品</b><small>先选分组，再搜索名称或编码</small></div><button type="button" class="cp-close" data-copy-close aria-label="关闭商品选择"><x-icon name="x" :size="17" /></button></header>
        <div class="cp-filters"><label class="cp-field"><span>商品分组</span><select class="select" data-copy-filter aria-label="复制商品分组"><option value="all">全部分组 · {{ $copyProducts->count() }} 个</option>
            @foreach ($groups as $root)
                @php($rootCopyCount = $copyGroups->where('root_id',$root->id)->sum(fn($bucket) => $bucket['products']->count()))
                @if ($rootCopyCount > 0)<optgroup label="{{ $root->name }}"><option value="root:{{ $root->id }}">{{ $root->name }}（全部 {{ $rootCopyCount }} 个）</option>@foreach($root->children as $child) @php($childCopyCount=$copyGroups->firstWhere('id',$child->id) ? $copyGroups->firstWhere('id',$child->id)['products']->count() : 0) @if($childCopyCount > 0)<option value="group:{{ $child->id }}">　{{ $child->name }}（{{ $childCopyCount }} 个）</option>@endif @endforeach</optgroup>@endif
            @endforeach
            @if($copyGroups->contains('id',0))<option value="group:0">未分组商品</option>@endif
        </select></label><label class="cp-field"><span>快速搜索</span><input class="input" type="search" data-copy-search placeholder="名称 / 编码 / 分组" autocomplete="off" aria-label="搜索要复制的商品"></label></div>
        <div class="cp-result-head"><span data-copy-count aria-live="polite">{{ $copyProducts->count() }} 个商品</span><button type="button" data-copy-reset>重置</button></div>
        <div class="cp-results">
            @foreach($copyGroups as $bucket)<details class="cp-group" data-copy-group data-group-id="{{ $bucket['id'] }}" data-root-id="{{ $bucket['root_id'] }}"><summary><x-icon name="chevron-right" :size="14" /><b>{{ $bucket['label'] }}</b><span data-copy-group-count>{{ $bucket['products']->count() }}</span></summary><div class="cp-group-items">
                @foreach($bucket['products'] as $copyProduct)<form method="POST" action="{{ route('admin.products.duplicate',$copyProduct) }}" data-copy-product data-copy-text="{{ $copyProduct->name.' '.$copyProduct->code.' '.$bucket['label'] }}">@csrf<button class="copy-product-item" type="submit"><span>{{ $copyProduct->name }}</span><small><span class="cp-code">{{ $copyProduct->code }}</span><span class="cp-status">{{ ['active'=>'上架','draft'=>'草稿','inactive'=>'下架'][$copyProduct->status] ?? $copyProduct->status }}</span><span class="cp-use">复制 <x-icon name="arrow-right" :size="12" /></span></small></button></form>@endforeach
            </div></details>@endforeach
            <div class="copy-product-empty" data-copy-empty @if($copyProducts->isNotEmpty()) hidden @endif><x-icon name="search" :size="21" /><b>没有匹配的商品</b><span>试试切换分组或缩短关键词。</span></div>
        </div>
        <footer class="cp-footer"><x-icon name="file" :size="14" /> 复制后生成草稿，确认配置后再上架。</footer>
    </div>
</details>
