
    <div class="page-head">
        <div><h1>订单财务</h1><p>订单、支付、账单与收入核对。</p></div>
        <div class="page-actions">
            <form method="GET" class="filter-bar order-query-toolbar" action="{{ url()->current() }}">
<input type="hidden" name="tab" value="{{ $workspaceTab ?? '' }}"><input type="hidden" name="section" value="{{ $section ?? '' }}">
                <input class="input" type="search" name="q" value="{{ $search }}" placeholder="订单号 / 客户">
                <select class="select" name="status"><option value="">全部状态</option><option value="pending" @selected($status === 'pending')>待支付</option><option value="paid" @selected($status === 'paid')>已支付</option><option value="processing" @selected($status === 'processing')>待处理（已支付）</option><option value="cancelled" @selected($status === 'cancelled')>已取消</option><option value="refunded" @selected($status === 'refunded')>已退款</option><option value="partially_refunded" @selected($status === 'partially_refunded')>部分退款</option></select>
                <select class="select" name="date_field" aria-label="日期类型"><option value="created_at" @selected($dateField === 'created_at')>下单日期</option><option value="paid_at" @selected($dateField === 'paid_at')>支付日期</option></select><label class="order-date-field"><span>从</span><input class="input" type="date" name="date_from" value="{{ $dateFrom }}" aria-label="开始日期"></label><label class="order-date-field"><span>至</span><input class="input" type="date" name="date_to" value="{{ $dateTo }}" aria-label="结束日期"></label>
                <button class="btn btn-ghost" type="submit">查询</button><a class="btn btn-ghost" href="{{ route('admin.workspace', ['area' => 'finance', 'tab' => 'orders']) }}">重置</a>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>订单号</th><th>客户</th><th>商品</th><th>金额</th><th>状态</th><th>下单 / 支付时间</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td><a class="mono strong" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_no }}</a></td>
                                <td class="small">{{ $order->user?->name }}<div class="sub">{{ $order->user?->email }}</div></td>
                                <td class="small">{{ $order->items_count }} 项</td>
                                <td class="strong">{{ \App\Support\Money::format($order->total) }}</td>
                                <td><x-status :status="$order->status" />@if ($order->status === 'paid' && ($order->has_pending_resource || $order->has_pending_supplier))<div class="order-pending-note">待处理</div>@endif</td>
                                <td class="small muted">{{ $order->created_at?->format('Y-m-d H:i') }}<div class="sub">支付 {{ $order->paid_at?->format('Y-m-d H:i') ?? '—' }}</div></td>
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

