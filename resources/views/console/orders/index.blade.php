@extends('layouts.console')

@section('title', '订单')
@section('page-title', '订单')

@section('content')
    <div class="page-head">
        <div><h1>订单</h1><p>查看订单状态、支付记录与开通结果。</p></div>
        <a class="btn btn-primary" href="{{ route('products.index') }}"><x-icon name="plus" :size="15" /> 新建订单</a>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>订单号</th><th>商品</th><th>金额</th><th>状态</th><th>创建时间</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="mono strong">{{ $order->order_no }}@if ($order->type === 'legacy_history')<div class="sub">旧站历史 · 仅供查询</div>@endif</td>
                                <td><span class="small">{{ $order->items_count }} 个商品</span></td>
                                <td class="strong">{{ \App\Support\Money::format($order->total) }}</td>
                                <td><x-status :status="$order->status" /></td>
                                <td class="small muted">{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('console.orders.show', $order) }}">详情</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">还没有订单，去选购一台云服务器吧。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $orders->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
