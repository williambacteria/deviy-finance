@extends('layouts.console')

@section('title', '订单详情')
@section('page-title', '订单详情')

@section('content')
    <div class="page-head">
        <div>
            <div class="flex items-center gap-3"><h1>订单 {{ $order->order_no }}</h1><x-status :status="$order->status" /></div>
            <p>创建于 {{ $order->created_at?->format('Y-m-d H:i') }} · 币种 {{ $order->currency }}</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('console.orders.index') }}">返回订单</a>
    </div>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h2>订单明细</h2><span class="meta">{{ $order->items->count() }} 项</span></div>
            <div class="panel-body tight">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>商品</th><th>周期</th><th>单价</th><th>数量</th><th class="num">小计</th></tr></thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td><span class="strong">{{ $item->product_name }}</span><div class="sub">{{ $item->product?->region }}</div></td>
                                    <td class="small">{{ \App\Support\Billing::label($item->billing_cycle) }}</td>
                                    <td>{{ \App\Support\Money::format($item->unit_price) }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td class="num strong">{{ \App\Support\Money::format($item->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel-body" style="border-top:1px solid var(--dt-e-eef3f9,#eef3f9)">
                    <div class="flex justify-between small" style="padding:4px 0;color:var(--dt-t-64748b,#64748b)"><span>小计</span><span>{{ \App\Support\Money::format($order->subtotal) }}</span></div>
                    <div class="flex justify-between small" style="padding:4px 0;color:var(--dt-t-64748b,#64748b)"><span>优惠</span><span>-{{ \App\Support\Money::format($order->discount) }}</span></div>
                    <div class="flex justify-between" style="padding:10px 0 0;border-top:1px solid var(--dt-e-edf2f7,#edf2f7);margin-top:8px"><b>合计</b><b style="font-size:22px">{{ \App\Support\Money::format($order->total) }}</b></div>
                </div>
            </div>
        </div>

        <div style="display:grid;gap:16px">
            <div class="panel">
                <div class="panel-head"><h2>支付</h2></div>
                <div class="panel-body">
                    @if ($order->type === 'legacy_history')
                        <div class="note info">此订单为旧站历史记录，仅供查询，不会再次扣费或开通。续费请前往对应实例办理。</div>
                    @elseif ($order->status !== 'pending' && $order->status !== 'paid')
                        <div class="note info">该订单已结束，当前不可支付。</div>
                    @elseif ($order->status === 'paid')
                        <div class="note info">订单已支付，服务已进入开通流程。</div>
                        <div class="flex justify-between small" style="padding:8px 0;color:var(--dt-t-64748b,#64748b)"><span>支付时间</span><b>{{ $order->paid_at?->format('Y-m-d H:i') }}</b></div>
                        @foreach ($order->payments as $payment)
                            <div class="flex justify-between small" style="padding:6px 0;color:var(--dt-t-64748b,#64748b)"><span>{{ ['alipay'=>'支付宝','wechat'=>'微信支付','balance'=>'余额'][$payment->gateway] ?? $payment->gateway }}</span><b class="mono">{{ $payment->payment_no }}</b></div>
                            @if (data_get($payment->payload,'settlement') === 'wallet_credit')<div class="note info">本笔额外收款已转入账户余额，未重复交付商品。</div>@endif
                        @endforeach
                    @else

                        <p class="muted small">选择支付宝、微信或账户余额完成订单付款，付款成功后自动进入交付流程。</p>
                        <div class="flex justify-between" style="padding:14px 0"><span class="small muted">应付金额</span><b style="font-size:24px">{{ \App\Support\Money::format($order->total) }}</b></div>
                        <button class="btn btn-primary btn-block" type="button" data-open-order-cashier>选择支付方式</button>
                        <p class="small muted" style="margin-top:12px">账户余额：{{ \App\Support\Money::format(auth()->user()->balance) }}</p>
                        <noscript><form method="POST" action="{{ route('console.orders.pay',$order) }}">@csrf<button class="btn" type="submit">余额支付</button></form><p>使用扫码支付请启用 JavaScript。</p></noscript>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>账单</h2></div>
                <div class="panel-body">
                    @forelse ($order->invoices as $invoice)
                        <div class="flex justify-between items-center" style="padding:7px 0">
                            <div><div class="strong small">{{ $invoice->invoice_no }}</div><div class="small muted">到期 {{ $invoice->due_at?->format('Y-m-d') }}</div></div>
                            <div style="text-align:right"><b>{{ \App\Support\Money::format($invoice->amount) }}</b><div><x-status :status="$invoice->status" /></div></div>
                        </div>
                    @empty
                        <div class="small muted">暂无账单。</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if ($order->type !== 'legacy_history' && $order->status === 'pending')
        @include('console.orders.cashier')
    @endif
@endsection
