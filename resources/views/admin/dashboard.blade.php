@extends('layouts.admin')

@section('title', '概览')
@section('page-title', '概览')

@section('content')
    <style>
        .rev-chart { display:flex; align-items:flex-end; gap:6px; height:180px; padding-top:14px; }
        .rev-col { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap:8px; height:100%; }
        .rev-bar { width:100%; max-width:34px;border-radius:6px 6px 3px 3px;background:linear-gradient(180deg,var(--dt-b-60a5fa,#60a5fa),var(--dt-b-2563eb,#2563eb)); min-height:2px; transition:height .2s; }
        .rev-label { font-size:9px;color:var(--dt-t-94a3b8,#94a3b8); white-space:nowrap; }
        .growth-up {color:var(--dt-t-047857,#047857);background:var(--dt-b-ecfdf5,#ecfdf5);border-radius:99px; padding:2px 7px; font-size:10px; font-weight:700; }
        .growth-down {color:var(--dt-t-b91c1c,#b91c1c);background:var(--dt-b-fef2f2,#fef2f2);border-radius:99px; padding:2px 7px; font-size:10px; font-weight:700; }
        .mini-stat { display:flex; justify-content:space-between; align-items:center; padding:10px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9); font-size:12px; }
        .mini-stat:last-child {border-bottom:0; }
    </style>

    <div class="page-head">
        <div><h1>运营概览</h1><p>收入、订单、续费、节点与通知的实时运营视图。</p></div>
        <div class="page-actions"><a class="btn btn-ghost" href="{{ route('admin.audit.index') }}">审计日志</a><a class="btn btn-primary" href="{{ route('admin.orders.index') }}">处理订单</a></div>
    </div>

    <div class="stat-grid">
        <div class="stat-card" style="--accent:#2563eb;--accent-soft:#eff6ff">
            <div class="stat-top"><span class="stat-label">本月收入</span><span class="stat-icon"><x-icon name="wallet" :size="17" /></span></div>
            <div class="stat-value">{{ \App\Support\Money::format($stats['month_revenue']) }}</div>
            <div class="stat-foot">
                <span class="{{ $stats['revenue_growth'] >= 0 ? 'growth-up' : 'growth-down' }}">{{ $stats['revenue_growth'] >= 0 ? '+' : '' }}{{ number_format($stats['revenue_growth'], 1) }}%</span>
                较上月 · 累计 {{ \App\Support\Money::format($stats['revenue']) }}
            </div>
        </div>
        <div class="stat-card" style="--accent:#d97706;--accent-soft:#fffbeb">
            <div class="stat-top"><span class="stat-label">待处理订单</span><span class="stat-icon"><x-icon name="receipt" :size="17" /></span></div>
            <div class="stat-value">{{ $stats['pending_orders'] }}</div>
            <div class="stat-foot">本月已支付 {{ $stats['orders_this_month'] }} 单</div>
        </div>
        <div class="stat-card" style="--accent:#7c3aed;--accent-soft:#f5f3ff">
            <div class="stat-top"><span class="stat-label">7 天内到期</span><span class="stat-icon"><x-icon name="clock" :size="17" /></span></div>
            <div class="stat-value">{{ $stats['expiring_services'] }}</div>
            <div class="stat-foot">已停机 {{ $stats['suspended_services'] }} 台</div>
        </div>
        <div class="stat-card" style="--accent:#0891b2;--accent-soft:#ecfeff">
            <div class="stat-top"><span class="stat-label">通知送达率</span><span class="stat-icon"><x-icon name="mail" :size="17" /></span></div>
            <div class="stat-value">{{ $stats['notification_rate'] }}%</div>
            <div class="stat-foot">成功 {{ $stats['notification_sent'] }} · 失败 {{ $stats['notification_failed'] }}</div>
        </div>
    </div>

    <div class="grid-2 mt-4">
        <div class="panel">
            <div class="panel-head"><h2>近 14 天收入</h2><span class="meta">净收入（扣除退款）</span></div>
            <div class="panel-body">
                <div class="rev-chart">
                    @foreach ($revenueTrend as $point)
                        <div class="rev-col" title="{{ $point['date'] }} · {{ \App\Support\Money::format($point['amount']) }}">
                            <div class="rev-bar" style="height: {{ $point['amount'] > 0 ? max(4, ($point['amount'] / $trendMax) * 100) : 2 }}%"></div>
                            <span class="rev-label">{{ $point['date'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between mt-3 small muted"><span>14 天前</span><span>今天</span></div>
                <div class="note info mt-3">本月退款 {{ \App\Support\Money::format($stats['refunded_this_month']) }}；累计收入按净额统计。</div>
            </div>
        </div>

        <div style="display:grid;gap:16px">
            <div class="panel">
                <div class="panel-head"><h2>服务状态</h2><span class="meta">{{ $stats['active_services'] }} 台运行中</span></div>
                <div class="panel-body">
                    @php($statusLabels = ['active' => '运行中', 'pending' => '待处理', 'provisioning' => '开通中', 'suspended' => '已暂停', 'stopped' => '已关机', 'terminated' => '已终止', 'failed' => '失败'])
                    @forelse ($serviceStatuses as $status => $count)
                        <div class="mini-stat"><span>{{ $statusLabels[$status] ?? $status }}</span><b>{{ $count }}</b></div>
                    @empty
                        <div class="small muted">暂无服务。</div>
                    @endforelse
                </div>
            </div>
            <div class="panel">
                <div class="panel-head"><h2>客户与支持</h2></div>
                <div class="panel-body">
                    <div class="mini-stat"><span>客户总数</span><b>{{ $stats['customers'] }}</b></div>
                    <div class="mini-stat"><span>待处理工单</span><b>{{ $stats['open_tickets'] }}</b></div>
                    <div class="mini-stat"><span>节点在线</span><b>{{ $stats['nodes_online'] }} / {{ $stats['nodes_total'] }}</b></div>
                    <div class="mini-stat"><span>最近对账</span><b>
                        @if ($latestReconciliation)
                            {{ \Illuminate\Support\Carbon::parse($latestReconciliation->run_date)->format('m-d') }} · {{ $latestReconciliation->issues_count === 0 ? '通过' : $latestReconciliation->issues_count.' 项差异' }}
                        @else
                            未运行
                        @endif
                    </b></div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid-2 mt-4">
        <div class="panel">
            <div class="panel-head"><h2>热销商品</h2><span class="meta">按已支付订单金额</span></div>
            <div class="panel-body tight">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>商品</th><th>订单数</th><th class="num">金额</th></tr></thead>
                        <tbody>
                            @forelse ($topProducts as $product)
                                <tr><td class="strong">{{ $product->product_name }}</td><td>{{ $product->qty }}</td><td class="num strong">{{ \App\Support\Money::format($product->amount) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="table-empty">暂无已支付订单。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>最近工单</h2><a class="small" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('admin.tickets.index') }}">全部 →</a></div>
            <div class="panel-body">
                @forelse ($recentTickets as $ticket)
                    <a class="flex items-center justify-between gap-3" style="padding:10px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)" href="{{ route('admin.tickets.show', $ticket) }}">
                        <div style="min-width:0">
                            <div class="strong small" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $ticket->subject }}</div>
                            <div class="small muted">{{ $ticket->user?->name }} · {{ $ticket->ticket_no }}</div>
                        </div>
                        <x-status :status="$ticket->status" />
                    </a>
                @empty
                    <div class="small muted">暂无工单。</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid-2 mt-4">
        <div class="panel">
            <div class="panel-head"><h2>最近订单</h2><a class="small" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('admin.orders.index') }}">全部 →</a></div>
            <div class="panel-body tight">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>订单</th><th>客户</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td><a class="mono strong" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_no }}</a></td>
                                    <td class="small">{{ $order->user?->name }}</td>
                                    <td class="strong">{{ \App\Support\Money::format($order->total) }}</td>
                                    <td><x-status :status="$order->status" /></td>
                                    <td class="small muted">{{ $order->created_at?->format('m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>节点状态</h2><span class="meta">{{ $stats['nodes_online'] }} / {{ $stats['nodes_total'] }} 在线</span></div>
            <div class="panel-body tight">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>节点</th><th>平台</th><th>状态</th><th>最后心跳</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($nodes as $node)
                                <tr>
                                    <td class="strong">{{ $node->name }}<div class="sub">{{ $node->region }}</div></td>
                                    <td><span class="chip">{{ strtoupper($node->provider) }}</span></td>
                                    <td><x-status :status="$node->status" /></td>
                                    <td class="small muted">{{ $node->last_heartbeat_at?->diffForHumans() ?? '—' }}</td>
                                    <td>
                                        @if ($node->agent_node_id)
                                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.controller.show', $node->agent_node_id) }}">查看</a>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
