
    <div class="page-head">
        <div><h1>财务报表</h1><p>收入、退款、余额流水导出与订单/支付/账单对账。</p></div>
        <div class="page-actions">
            <a class="btn btn-ghost" href="{{ route('admin.reports.export', ['type' => 'orders', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">导出订单 CSV</a>
            <a class="btn btn-ghost" href="{{ route('admin.reports.export', ['type' => 'ledger', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">导出流水 CSV</a>
            <a class="btn btn-primary" href="{{ route('admin.reports.export', ['type' => 'summary', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">导出汇总 CSV</a>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body">
            <form method="GET" class="filter-bar" style="align-items:flex-end" action="{{ url()->current() }}">
<input type="hidden" name="tab" value="{{ $workspaceTab ?? '' }}"><input type="hidden" name="section" value="{{ $section ?? '' }}">
                <div class="form-field"><label class="form-label">开始日期</label><input class="input" type="date" name="from" value="{{ $from->toDateString() }}"></div>
                <div class="form-field"><label class="form-label">结束日期</label><input class="input" type="date" name="to" value="{{ $to->toDateString() }}"></div>
                <button class="btn btn-primary" type="submit">查询</button>
                <a class="btn btn-ghost" href="{{ route('admin.reports.index') }}">本月</a>
            </form>
        </div>
    </div>

    <div class="stat-grid mt-4">
        <div class="stat-card"><div class="stat-top"><span class="stat-label">订单金额</span></div><div class="stat-value">{{ \App\Support\Money::format($stats['gross']) }}</div><div class="stat-foot">{{ $stats['orders'] }} 个已支付订单</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">退款</span></div><div class="stat-value">{{ \App\Support\Money::format($stats['refunds']) }}</div><div class="stat-foot">已退回客户余额</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">净收入</span></div><div class="stat-value">{{ \App\Support\Money::format($stats['net']) }}</div><div class="stat-foot">订单金额 - 退款</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">余额流水净额</span></div><div class="stat-value">{{ \App\Support\Money::format($stats['ledger']) }}</div><div class="stat-foot">含充值、支付、退款、调账</div></div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head">
            <h2>对账检查</h2>
            @if (count($reconciliation['issues']) === 0)
                <span class="badge active">通过 · 检查 {{ $reconciliation['checked'] }} 单</span>
            @else
                <span class="badge failed">发现 {{ count($reconciliation['issues']) }} 项差异 · 检查 {{ $reconciliation['checked'] }} 单</span>
            @endif
        </div>
        <div class="panel-body">
            @if (count($reconciliation['issues']) === 0)
                <div class="note info">订单金额、支付单、账单已付金额、余额流水净额一致。</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>订单</th><th>客户</th><th>差异类型</th><th class="num">应有</th><th class="num">实际</th></tr></thead>
                        <tbody>
                            @foreach (array_slice($reconciliation['issues'], 0, 20) as $issue)
                                <tr>
                                    <td class="mono">{{ $issue['order_no'] }}</td>
                                    <td>{{ $issue['customer'] }}</td>
                                    <td><span class="badge failed">{{ $issue['type'] }}</span></td>
                                    <td class="num">{{ \App\Support\Money::format($issue['expected']) }}</td>
                                    <td class="num">{{ \App\Support\Money::format($issue['actual']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (count($reconciliation['issues']) > 20)
                    <div class="note warn mt-3">仅显示前 20 项，共 {{ count($reconciliation['issues']) }} 项。</div>
                @endif
            @endif
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>每日对账快照</h2><span class="meta">最近 10 次</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>对账日期</th><th>检查订单</th><th>差异</th><th>状态</th><th>告警</th><th>运行时间</th></tr></thead>
                    <tbody>
                        @forelse ($reconciliationRuns as $run)
                            <tr>
                                <td class="mono">{{ $run->run_date }}</td>
                                <td>{{ $run->checked }}</td>
                                <td>{{ $run->issues_count }}</td>
                                <td>
                                    @if ($run->status === 'passed')
                                        <span class="badge active">通过</span>
                                    @else
                                        <span class="badge failed">有差异</span>
                                    @endif
                                </td>
                                <td>{{ $run->notified ? '已发送' : '—' }}</td>
                                <td class="small muted">{{ $run->created_at?->format('m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">暂无每日对账快照。可执行 <span class="mono">php artisan finance:reconcile</span>。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>按日汇总</h2><span class="meta">{{ $from->format('Y-m-d') }} 至 {{ $to->format('Y-m-d') }}</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>日期</th><th>订单数</th><th class="num">订单金额</th><th class="num">退款</th><th class="num">净收入</th></tr></thead>
                    <tbody>
                        @forelse ($daily as $row)
                            <tr>
                                <td class="mono">{{ $row->day }}</td>
                                <td>{{ $row->orders }}</td>
                                <td class="num">{{ \App\Support\Money::format($row->gross) }}</td>
                                <td class="num">{{ \App\Support\Money::format($row->refunds) }}</td>
                                <td class="num strong">{{ \App\Support\Money::format($row->net) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">该时间段暂无已支付订单。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

