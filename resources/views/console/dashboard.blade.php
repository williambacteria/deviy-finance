@extends('layouts.console')

@section('title', '概览')
@section('page-title', '概览')

@section('content')
    <div class="page-head">
        <div><div class="azure-eyebrow">WORKSPACE OVERVIEW</div><h1>{{ auth()->user()->name }}，欢迎回来。</h1><p>从这里掌握云资源、账单与服务动态。</p></div>
        <div class="page-actions"><a class="btn btn-ghost" href="{{ route('console.tickets.create') }}"><x-icon name="ticket" :size="15" /> 提交工单</a><a class="btn btn-primary" href="{{ route('products.index') }}"><x-icon name="plus" :size="15" /> 选购云资源</a></div>
    </div>
    <div class="azure-overview-top">
        <section class="azure-balance" aria-label="账户余额">
            <div class="azure-balance-label"><x-icon name="wallet" :size="16" /> 账户可用余额 <span class="currency">CNY</span></div>
            <div class="azure-balance-value">{{ \App\Support\Money::format(auth()->user()->balance) }}</div>
            <div class="azure-balance-foot"><span>安心部署，从容管理。</span><div><a class="btn" href="{{ route('console.invoices.index') }}">查看账单</a><a class="btn" href="{{ route('console.recharge.index') }}"><x-icon name="plus" :size="13" /> 账户充值</a></div></div>
        </section>
        <section class="azure-welcome">
            <div class="azure-eyebrow">BUILT FOR YOUR NEXT IDEA</div><h2>云上每一步，都心中有数。</h2>
            <p>{{ $stats['active_services'] }} 台服务正在运行，{{ $stats['pending_services'] }} 台正在开通。<br>{{ $stats['unpaid_amount'] > 0 ? '你有待支付账单，请在到期前完成支付。' : '当前暂无待支付账单，让每一笔支出清晰有序。' }}</p>
            <a class="azure-verified" href="{{ route('console.real-name') }}"><x-icon name="shield" :size="14" />{{ ['verified' => '账户已完成实名认证', 'pending' => '实名认证审核中', 'rejected' => '实名认证未通过，请重新提交'][auth()->user()->real_name_status] ?? '完成实名认证，开启完整云服务' }}</a>
        </section>
    </div>

    <div class="stat-grid">
        <div class="stat-card" style="--accent:#2563eb;--accent-soft:#eff6ff">
            <div class="stat-top"><span class="stat-label">运行中服务</span><span class="stat-icon"><x-icon name="server" :size="17" /></span></div>
            <div class="stat-value">{{ $stats['active_services'] }}</div>
            <div class="stat-foot">{{ $stats['pending_services'] }} 个正在开通</div>
        </div>
        <div class="stat-card" style="--accent:#0891b2;--accent-soft:#ecfeff">
            <div class="stat-top"><span class="stat-label">待支付金额</span><span class="stat-icon"><x-icon name="wallet" :size="17" /></span></div>
            <div class="stat-value">{{ \App\Support\Money::format($stats['unpaid_amount']) }}</div>
            <div class="stat-foot">账单到期前请完成支付</div>
        </div>
        <div class="stat-card" style="--accent:#7c3aed;--accent-soft:#f5f3ff">
            <div class="stat-top"><span class="stat-label">工单</span><span class="stat-icon"><x-icon name="ticket" :size="17" /></span></div>
            <div class="stat-value">{{ $stats['open_tickets'] }}</div>
            <div class="stat-foot">客服工作时间内响应</div>
        </div>
        <div class="stat-card" style="--accent:#059669;--accent-soft:#ecfdf5">
            <div class="stat-top"><span class="stat-label">账户等级</span><span class="stat-icon"><x-icon name="shield" :size="17" /></span></div>
            <div class="stat-value" style="font-size:22px">{{ auth()->user()->customer_group === 'vip' ? 'VIP 客户' : '标准客户' }}</div>
            <div class="stat-foot">{{ ['verified' => '已实名认证', 'pending' => '实名审核中', 'rejected' => '实名未通过'][auth()->user()->real_name_status] ?? '待实名认证' }} · <a href="{{ route('console.real-name') }}">查看</a></div>
        </div>
    </div>

    <div class="azure-dashboard-content">
        <div class="panel">
            <div class="panel-head">
                <h2>我的服务</h2>
                <a class="small" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('console.services.index') }}">全部服务 →</a>
            </div>
            <div class="panel-body">
                <div class="azure-services-caption">最近创建的服务 · 连接、配置与到期信息</div><div class="cloud-resource-list cloud-resource-list--overview">
                @forelse ($services as $service)
                    @include('console.services.card')
                @empty
                    <div class="empty-state">
                        <div class="empty-icon"><x-icon name="server" :size="22" /></div>
                        <h3>还没有云资源</h3>
                        <p>选择适合业务的云服务器、裸金属或 CDN 套餐。</p>
                        <a class="btn btn-primary btn-sm" href="{{ route('products.index') }}">去选购</a>
                    </div>
                @endforelse
                </div>
            </div>
        </div>

        <div class="azure-dashboard-stack">
            <div class="panel">
                <div class="panel-head"><h2>待支付账单</h2><a class="small" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('console.invoices.index') }}">全部 →</a></div>
                <div class="panel-body">
                    @forelse ($invoices as $invoice)
                        <div class="flex items-center justify-between gap-3" style="padding:10px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)">
                            <div>
                                <div class="strong small">{{ $invoice->invoice_no }}</div>
                                <div class="small muted">{{ $invoice->due_at?->format('Y-m-d') }} 到期</div>
                            </div>
                            <div style="text-align:right">
                                <div class="strong">{{ \App\Support\Money::format($invoice->amount) }}</div>
                                <x-status :status="$invoice->status" />
                            </div>
                        </div>
                    @empty
                        <div class="azure-bill-empty"><x-icon name="shield" :size="17" /> 暂无待支付账单，账户账务清晰。</div>
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>最近工单</h2><a class="small" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('console.tickets.index') }}">全部 →</a></div>
                <div class="panel-body">
                    @forelse ($tickets as $ticket)
                        <a class="flex items-center justify-between gap-3" style="padding:9px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)" href="{{ route('console.tickets.show', $ticket) }}">
                            <div>
                                <div class="strong small">{{ $ticket->subject }}</div>
                                <div class="small muted">{{ $ticket->ticket_no }}</div>
                            </div>
                            <x-status :status="$ticket->status" />
                        </a>
                    @empty
                        <div class="small muted" style="padding:8px 0">暂无工单。</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>站点公告</h2><a class="small" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('news.index') }}">全部 →</a></div>
        <div class="panel-body">
            @foreach ($announcements as $announcement)
                <a class="flex items-center justify-between gap-3 azure-announcement" style="padding:9px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)" href="{{ route('news.show', $announcement) }}">
                    <div class="flex items-center gap-3">
                        <span class="badge info">{{ $announcement->type === 'news' ? '新闻' : '公告' }}</span>
                        <span class="small">{{ $announcement->title }}</span>
                    </div>
                    <span class="small muted">{{ $announcement->published_at?->format('m-d') }}</span>
                </a>
            @endforeach
        </div>
    </div>
    <div class="azure-shortcuts">
        <a href="{{ route('products.index') }}"><x-icon name="package" :size="23" /><div><b>选购云资源</b><small>为下一次增长做好准备</small></div><span class="azure-shortcut-arrow">→</span></a>
        <a href="{{ route('console.tickets.create') }}"><x-icon name="ticket" :size="23" /><div><b>提交工单</b><small>产品与资源问题，联系支持团队</small></div><span class="azure-shortcut-arrow">→</span></a>
        <a href="{{ route('console.orders.index') }}"><x-icon name="receipt" :size="23" /><div><b>查看订单</b><small>每一笔支出，都清晰可见</small></div><span class="azure-shortcut-arrow">→</span></a>
    </div>
@endsection
