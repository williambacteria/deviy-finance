<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ filemtime(public_path('assets/js/theme-init.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/fonts/noto-sc/noto-sc.css') }}?v=20261117">

    <title>{{ $invoice->invoice_no }} · 账单</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", "Microsoft YaHei", sans-serif;background:var(--dt-b-f1f5f9,#f1f5f9);color:var(--dt-t-0f172a,#0f172a); }
        .sheet { width: 820px; max-width: calc(100% - 32px); margin: 32px auto;background:var(--dt-b-ffffff,#ffffff); padding: 44px 50px; box-shadow: 0 18px 50px rgba(15,23,42,.12); }
        .top { display: flex; justify-content: space-between; gap: 30px;border-bottom:2px solid var(--dt-e-0f172a,#0f172a); padding-bottom: 22px; }
        .brand { font-size: 22px; font-weight: 750; letter-spacing: -.3px; }
        .brand small { display: block; margin-top: 5px;color:var(--dt-t-64748b,#64748b); font-size: 10px; letter-spacing: 2px; font-weight: 500; }
        .meta { text-align: right; font-size: 11px;color:var(--dt-t-64748b,#64748b); line-height: 1.8; }
        .meta b {color:var(--dt-t-0f172a,#0f172a); font-size: 14px; }
        .title { display: flex; justify-content: space-between; align-items: flex-end; margin: 28px 0 18px; }
        .title h1 { margin: 0; font-size: 30px; letter-spacing: 2px; }
        .title .status { font-size: 12px;color:var(--dt-t-047857,#047857);background:var(--dt-b-ecfdf5,#ecfdf5);border-radius:99px; padding: 5px 12px; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 22px 0 26px; }
        .label {color:var(--dt-t-94a3b8,#94a3b8); font-size: 10px; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px; }
        .party b { font-size: 14px; display: block; margin-bottom: 3px; }
        .party div {color:var(--dt-t-475569,#475569); font-size: 11px; line-height: 1.7; }
        table { width: 100%;border-collapse:collapse; }
        th { text-align: left;background:var(--dt-b-f8fafc,#f8fafc);color:var(--dt-t-64748b,#64748b); font-size: 10px; letter-spacing: .6px; padding: 10px 8px;border-bottom:1px solid var(--dt-e-e2e8f0,#e2e8f0); }
        td { padding: 12px 8px;border-bottom:1px solid var(--dt-e-eef2f7,#eef2f7); font-size: 12px; }
        td.num, th.num { text-align: right; }
        .total { width: 300px; margin-left: auto; margin-top: 18px; }
        .total .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px;color:var(--dt-t-64748b,#64748b); }
        .total .row.grand {border-top:1px solid var(--dt-e-0f172a,#0f172a); margin-top: 8px; padding-top: 12px;color:var(--dt-t-0f172a,#0f172a); font-size: 18px; font-weight: 700; }
        .foot { margin-top: 42px;border-top:1px solid var(--dt-e-e2e8f0,#e2e8f0); padding-top: 16px;color:var(--dt-t-94a3b8,#94a3b8); font-size: 10px; line-height: 1.8; }
        .actions { width: 820px; max-width: calc(100% - 32px); margin: 0 auto 40px; display: flex; gap: 8px; justify-content: flex-end; }
        .btn {border:1px solid var(--dt-e-cbd5e1,#cbd5e1);background:var(--dt-b-ffffff,#ffffff);border-radius:8px; padding: 9px 14px; font-size: 12px; cursor: pointer; text-decoration: none;color:var(--dt-t-0f172a,#0f172a); }
        .btn.primary {background:var(--dt-b-0f172a,#0f172a);border-color:var(--dt-e-0f172a,#0f172a);color:var(--dt-t-ffffff,#ffffff); }
        @media print {
            body {background:var(--dt-b-ffffff,#ffffff); }
            .sheet { box-shadow: none; margin: 0; width: 100%; max-width: none; padding: 20mm 18mm; }
            .actions { display: none; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('assets/css/azure.css') }}?v={{ filemtime(public_path('assets/css/azure.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}?v={{ filemtime(public_path('assets/css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/polish.css') }}?v={{ filemtime(public_path('assets/css/polish.css')) }}">
</head>
<body class="azure-invoice">
    <div class="sheet">
        <div class="top">
            <div class="brand">{{ $settings['site_name'] ?? 'Deviy Cloud' }}<small>CLOUD INFRASTRUCTURE</small></div>
            <div class="meta">
                <b>{{ $invoice->invoice_no }}</b><br>
                开票日期：{{ $invoice->created_at?->format('Y-m-d') }}<br>
                到期日期：{{ $invoice->due_at?->format('Y-m-d') ?? '—' }}
            </div>
        </div>

        <div class="title">
            <h1>账单 / INVOICE</h1>
            <span class="status">{{ $invoice->status === 'paid' ? '已支付' : '待支付' }}</span>
        </div>

        <div class="parties">
            <div class="party">
                <div class="label">Bill To</div>
                <b>{{ $invoice->user?->name }}</b>
                <div>{{ $invoice->user?->company ?: '个人客户' }}<br>{{ $invoice->user?->email }}<br>{{ $invoice->user?->phone }}</div>
            </div>
            <div class="party">
                <div class="label">From</div>
                <b>{{ $settings['site_name'] ?? 'Deviy Cloud' }}</b>
                <div>{{ $settings['support_email'] ?? 'support@deviy.cn' }}<br>{{ $settings['site_url'] ?? 'https://www.deviy.cn' }}</div>
            </div>
        </div>

        <table>
            <thead><tr><th>项目</th><th>周期</th><th class="num">金额</th></tr></thead>
            <tbody>
                @foreach (($invoice->order?->items ?? collect()) as $item)
                    <tr><td>{{ $item->product_name }}</td><td>{{ \App\Support\Billing::label($item->billing_cycle) }}</td><td class="num">{{ \App\Support\Money::format($item->amount) }}</td></tr>
                @endforeach
                @if (! $invoice->order?->items?->count())
                    @foreach (($invoice->items ?? []) as $item)
                        <tr><td>{{ $item['name'] ?? '云服务' }}</td><td>—</td><td class="num">¥{{ number_format((float) ($item['amount'] ?? 0), 2) }}</td></tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <div class="total">
            <div class="row"><span>小计</span><span>{{ \App\Support\Money::format($invoice->amount) }}</span></div>
            <div class="row"><span>已支付</span><span>{{ \App\Support\Money::format($invoice->paid_amount) }}</span></div>
            <div class="row grand"><span>{{ $invoice->status === 'paid' ? '已结清' : '应付' }}</span><span>{{ $invoice->status === 'paid' ? '¥0.00' : \App\Support\Money::format($invoice->amount) }}</span></div>
        </div>

        <div class="foot">
            本账单由 {{ $settings['site_name'] ?? 'Deviy Cloud' }} 系统生成。如需发票抬头或税号，请在控制台提交工单。<br>
            {{ $settings['site_slogan'] ?? '稳定、透明、可长期依赖的云服务' }}
        </div>
    </div>

    <div class="actions">
        @include('partials.theme-toggle')
        <a class="btn" href="{{ route('console.invoices.index') }}">返回账单</a>
        <button class="btn primary" type="button" data-print>打印 / 保存 PDF</button>
    </div>
    <script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}" defer></script>
</body>
</html>
