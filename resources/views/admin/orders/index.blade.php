@extends('layouts.admin')

@section('title', '订单财务')
@section('page-title', '订单财务')

@section('content')
    <div class="page-head">
        <div><h1>订单财务</h1><p>订单、支付、账单与收入核对。</p></div>
        <div class="page-actions">
            <form method="GET" class="filter-bar">
                <input class="input" type="search" name="q" value="{{ $search }}" placeholder="订单号 / 客户">
                <select class="select" name="status"><option value="">全部状态</option><option value="pending" @selected($status === 'pending')>待支付</option><option value="paid" @selected($status === 'paid')>已支付</option></select>
                <button class="btn btn-ghost" type="submit">筛选</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>订单号</th><th>客户</th><th>商品</th><th>金额</th><th>状态</th><th>支付时间</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td><a class="mono strong" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_no }}</a></td>
                                <td class="small">{{ $order->user?->name }}<div class="sub">{{ $order->user?->email }}</div></td>
                                <td class="small">{{ $order->items_count }} 项</td>
                                <td class="strong">{{ \App\Support\Money::format($order->total) }}</td>
                                <td><x-status :status="$order->status" /></td>
                                <td class="small muted">{{ $order->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('admin.orders.show', $order) }}">详情</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">没有匹配的订单。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $orders->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
