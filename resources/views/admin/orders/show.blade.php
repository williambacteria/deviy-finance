@extends('layouts.admin')

@section('title', '订单详情')
@section('page-title', '订单详情')

@section('content')
    <div class="page-head">
        <div><div class="flex items-center gap-3"><h1>{{ $order->order_no }}</h1><x-status :status="$order->status" /></div><p>{{ $order->user?->name }} · {{ $order->created_at?->format('Y-m-d H:i') }} · {{ $order->currency }}</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.orders.index') }}">返回列表</a>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h2>订单明细</h2></div>
            <div class="panel-body tight">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>商品</th><th>周期</th><th>单价</th><th>数量</th><th class="num">金额</th></tr></thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr><td class="strong">{{ $item->product_name }}<div class="sub">{{ $item->product?->region }}</div></td><td>{{ \App\Support\Billing::label($item->billing_cycle) }}</td><td>{{ \App\Support\Money::format($item->unit_price) }}</td><td>{{ $item->quantity }}</td><td class="num strong">{{ \App\Support\Money::format($item->amount) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel-body" style="border-top:1px solid var(--dt-e-eef3f9,#eef3f9)">
                    <div class="flex justify-between small" style="padding:4px 0;color:var(--dt-t-64748b,#64748b)"><span>小计</span><span>{{ \App\Support\Money::format($order->subtotal) }}</span></div>
                    <div class="flex justify-between small" style="padding:4px 0;color:var(--dt-t-64748b,#64748b)"><span>优惠</span><span>-{{ \App\Support\Money::format($order->discount) }}</span></div>
                    <div class="flex justify-between small" style="padding:4px 0;color:var(--dt-t-64748b,#64748b)"><span>已退款</span><span>{{ \App\Support\Money::format($order->refunded_amount) }}</span></div>
                    <div class="flex justify-between" style="padding-top:10px;border-top:1px solid var(--dt-e-edf2f7,#edf2f7);margin-top:8px"><b>合计</b><b style="font-size:22px">{{ \App\Support\Money::format($order->total) }}</b></div>
                </div>
            </div>
        </div>
        <div style="display:grid;gap:16px">
            <div class="panel">
                <div class="panel-head"><h2>客户</h2></div>
                <div class="panel-body">
                    <table class="spec-table">
                        <tr><td>姓名</td><td><a href="{{ route('admin.customers.show', $order->user) }}" style="color:var(--dt-t-2563eb,#2563eb)">{{ $order->user?->name }}</a></td></tr>
                        <tr><td>邮箱</td><td>{{ $order->user?->email }}</td></tr>
                        <tr><td>余额</td><td>{{ \App\Support\Money::format($order->user?->balance ?? 0) }}</td></tr>
                    </table>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head"><h2>支付与账单</h2></div>
                <div class="panel-body">
                    @foreach ($order->payments as $payment)
                        <div class="flex justify-between" style="padding:6px 0;font-size:12px"><span>{{ $payment->gateway }} · {{ $payment->payment_no }}</span><b>{{ \App\Support\Money::format($payment->amount) }}</b></div>
                    @endforeach
                    @foreach ($order->invoices as $invoice)
                        <div class="flex justify-between" style="padding:6px 0;font-size:12px"><span>{{ $invoice->invoice_no }}</span><x-status :status="$invoice->status" /></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if (in_array($order->status, ['paid', 'partially_refunded'], true) && auth('admin')->user()->canManageFinance())
        <div class="panel mt-4">
            <div class="panel-head"><h2>订单退款</h2><span class="meta">退回客户余额</span></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="filter-bar" style="align-items:flex-end">
                    @csrf
                    <div class="form-field"><label class="form-label">退款金额</label><input class="input" type="number" step="0.01" min="0.01" max="{{ (float) $order->total - (float) $order->refunded_amount }}" name="amount" required></div>
                    <div class="form-field" style="flex:1"><label class="form-label">退款原因</label><input class="input" name="reason" placeholder="客户申请 / 服务异常" required></div>
                    <button class="btn btn-danger" type="submit" data-confirm="确认退款？金额将退回客户余额。">确认退款</button>
                </form>
                <div class="note warn mt-3">当前退款退回账户余额，不自动调用支付渠道；退款会写入流水与审计日志。</div>
            </div>
        </div>
    @endif
@endsection
