@extends('layouts.admin')

@section('title', '商品设置')
@section('page-title', '商品设置')

@section('content')
    <div class="page-head">
        <div><h1>商品设置</h1><p>按二级分组筛选商品，在一个列表内统一管理价格、库存、规格和状态。</p></div>
        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('admin.products.create') }}">新建商品</a>
            @include('admin.products.partials.copy-picker')
        </div>
    </div>

    @if (session('status'))
        <div class="note success" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    @php
        $typeLabels = ['cloud_vm' => '云服务器', 'nat_vm' => 'NAT', 'game_server' => '游戏服务器', 'bare_metal' => '裸金属服务器', 'cdn' => 'CDN 加速', 'dedicated_ip' => '独立 IP', 'bandwidth' => '带宽', 'other' => '其他'];
        $selectedGroupLabel = '全部分组';
        $selectedRootId = null;
        foreach ($groups as $group) {
            if ($groupId === $group->id) {
                $selectedGroupLabel = $group->name.'（全部）';
                $selectedRootId = $group->id;
                break;
            }
            $selectedChild = $group->children->firstWhere('id', $groupId);
            if ($selectedChild) {
                $selectedGroupLabel = $group->name.' / '.$selectedChild->name;
                $selectedRootId = $group->id;
                break;
            }
        }
    @endphp

    <div class="panel product-list-panel">
        <div class="product-list-toolbar">
            <div class="product-list-title"><h2>商品列表</h2><span>共 {{ $products->count() }} 个商品</span></div>
            <form method="GET" class="product-filter-form" data-auto-filter>
                <input class="input" type="search" name="q" value="{{ $search }}" placeholder="搜索商品名称 / 编码">
                <div class="group-picker" data-group-picker>
                    <input type="hidden" name="group_id" value="{{ $groupId ?: '' }}" data-group-picker-value>
                    <button class="group-picker-toggle" type="button" data-group-picker-toggle aria-expanded="false">
                        <span data-group-picker-label>{{ $selectedGroupLabel }}</span>
                        <i class="group-picker-chevron"></i>
                    </button>
                    <div class="group-picker-panel" data-group-picker-panel hidden>
                        <button class="group-picker-all {{ $groupId === 0 ? 'is-selected' : '' }}" type="button" data-group-option="" data-label="全部分组">全部分组</button>
                        @foreach ($groups as $group)
                            <div class="group-picker-group {{ $selectedRootId === $group->id ? 'is-open' : '' }}">
                                <button class="group-picker-root" type="button" data-group-root>
                                    <b>{{ $group->name }}</b>
                                    <i class="group-picker-chevron"></i>
                                </button>
                                <div class="group-picker-children">
                                    <button class="group-picker-option {{ $groupId === $group->id ? 'is-selected' : '' }}" type="button" data-group-option="{{ $group->id }}" data-label="{{ $group->name }}（全部）">{{ $group->name }}（全部）</button>
                                    @foreach ($group->children as $child)
                                        <button class="group-picker-option {{ $groupId === $child->id ? 'is-selected' : '' }}" type="button" data-group-option="{{ $child->id }}" data-label="{{ $group->name }} / {{ $child->name }}">{{ $group->name }} / {{ $child->name }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <select class="select" name="type">
                    <option value="">全部类型</option>
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="select" name="status">
                    <option value="">全部状态</option>
                    <option value="active" @selected($status === 'active')>上架</option>
                    <option value="draft" @selected($status === 'draft')>草稿</option>
                    <option value="inactive" @selected($status === 'inactive')>下架</option>
                </select>
                <button class="btn btn-primary" type="submit">筛选</button>
                @if ($search !== '' || $status !== '' || $type !== '' || $groupId > 0)
                    <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">重置</a>
                @endif
            </form>
        </div>

@php($productsByGroup = $products->groupBy('product_group_id'))

        <div class="panel-body tight">
            <div class="pg-tree" data-product-sort-root data-sort-url="{{ route('admin.product-groups.products.sort') }}">
                @forelse ($groups as $root)
                    @php($rootProducts = $productsByGroup->get($root->id, collect()))
                    @php($rootCount = $rootProducts->count() + $root->children->sum(fn ($child) => $productsByGroup->get($child->id, collect())->count()))
                    <section class="pg-group" data-group-block data-group-id="{{ $root->id }}">
                        <div class="pg-group-head pg-root-head">
                            <button class="pg-collapse" type="button" data-group-collapse aria-expanded="true" aria-label="折叠{{ $root->name }}">
                                <span class="pg-chevron">⌄</span>
                            </button>
                            <div class="pg-group-title">
                                <b>{{ $root->name }}</b>
                                <span>{{ $rootCount }} 个商品</span>
                            </div>
                        </div>
                        <div class="pg-group-body">
                            @if ($rootProducts->isNotEmpty())
                                <div class="pg-child-heading">直属商品</div>
                                <div class="pg-products" data-product-list data-group-id="{{ $root->id }}">
                                    @foreach ($rootProducts as $product)
                                        @include('admin.products.partials.sort-row', ['product' => $product, 'groupId' => $root->id])
                                    @endforeach
                                </div>
                            @endif
                            @foreach ($root->children as $child)
                                @php($childProducts = $productsByGroup->get($child->id, collect()))
                                <section class="pg-child" data-group-block data-group-id="{{ $child->id }}">
                                    <div class="pg-group-head pg-child-head">
                                        <button class="pg-collapse" type="button" data-group-collapse aria-expanded="true" aria-label="折叠{{ $child->name }}">
                                            <span class="pg-chevron">⌄</span>
                                        </button>
                                        <div class="pg-group-title">
                                            <b>{{ $child->name }}</b>
                                            <span>{{ $childProducts->count() }} 个商品</span>
                                        </div>
                                    </div>
                                    <div class="pg-group-body">
                                        <div class="pg-products" data-product-list data-group-id="{{ $child->id }}">
                                            @forelse ($childProducts as $product)
                                                @include('admin.products.partials.sort-row', ['product' => $product, 'groupId' => $child->id])
                                            @empty
                                                <div class="pg-empty">该分组暂无商品</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="pg-empty">还没有商品分组，请先创建分组。</div>
                @endforelse
                @if ($products->isEmpty())
                    <div class="pg-empty">没有符合条件的商品。</div>
                @endif
            </div>
        </div>
    </div>
    <script src="{{ asset('assets/js/product-filters.js') }}" defer></script>
    @push('scripts')
        <script src="{{ asset('assets/js/product-groups.js') }}?v={{ filemtime(public_path('assets/js/product-groups.js')) }}" defer></script>
    @endpush
@endsection
