@extends('layouts.console')

@section('title', '账单与发票')
@section('page-title', '账单与发票')

@section('content')
    <div class="page-head">
        <div><h1>账单与发票</h1><p>查看历史账单、支付状态与发票申请入口。</p></div>
        <a class="btn btn-ghost" href="{{ route('console.orders.index') }}">查看订单</a>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>账单号</th><th>关联订单</th><th>金额</th><th>已付</th><th>状态</th><th>到期时间</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="mono strong">{{ $invoice->invoice_no }}</td>
                                <td class="mono small">{{ $invoice->order?->order_no ?? '—' }}</td>
                                <td class="strong">{{ \App\Support\Money::format($invoice->amount) }}</td>
                                <td>{{ \App\Support\Money::format($invoice->paid_amount) }}</td>
                                <td><x-status :status="$invoice->status" /></td>
                                <td class="small muted">{{ $invoice->due_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="actions">
                                    <a class="btn btn-ghost btn-sm" href="{{ route('console.invoices.show', $invoice) }}">查看 / 打印</a>
                                    @if ($invoice->order)
                                        <a class="btn btn-ghost btn-sm" href="{{ route('console.orders.show', $invoice->order) }}">查看订单</a>
                                    @endif
                                    <button class="btn btn-soft btn-sm" type="button" data-alert="发票申请将在财务模块接入后启用。">申请发票</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">暂无账单。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $invoices->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
