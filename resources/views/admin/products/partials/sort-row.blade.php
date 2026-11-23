<div class="pg-product" draggable="true" data-product-id="{{ $product->id }}" data-group-id="{{ $groupId }}">
    <span class="pg-drag-handle" title="拖动排序">⋮⋮</span>
    <div class="pg-product-main">
        <a href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a>
        <span class="mono">{{ $product->code }}</span>
    </div>
    <span class="pg-product-price">{{ \App\Support\Money::format($product->prices->firstWhere('billing_cycle', 'monthly')?->price ?? 0) }}/月</span>
    <span class="pg-product-stock">库存 {{ $product->stock }}</span>
    <x-status :status="$product->status" :label="['active' => '上架', 'draft' => '草稿', 'inactive' => '下架'][$product->status] ?? $product->status" />
    <div class="pg-product-actions">
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.products.edit', $product) }}">编辑</a>
        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-confirm="确认删除该商品？已有服务的商品不能删除，请改为下架。">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger btn-sm" type="submit">删除</button>
        </form>
    </div>
</div>
