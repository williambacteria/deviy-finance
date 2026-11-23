@extends('layouts.console')

@section('title', '账户充值')
@section('page-title', '账户充值')

@section('content')
    <div class="page-head">
        <div><h1>账户充值</h1><p>选择已开通的支付渠道扫码充值，支付成功后余额自动到账。</p></div>
        <div class="page-actions"><span class="chip">当前余额 <b>{{ \App\Support\Money::format(auth()->user()->balance) }}</b></span></div>
    </div>


    @if ($selectedPayment)
        <section class="panel" style="margin-bottom:20px" data-recharge-detail>
            <div class="panel-head"><h2>充值记录详情</h2><span class="meta">{{ $selectedPayment->payment_no }}</span></div>
            <div class="panel-body"><div class="recharge-detail-grid"><div><span class="muted small">充值金额</span><strong>{{ \App\Support\Money::format($selectedPayment->amount) }}</strong></div><div><span class="muted small">支付渠道</span><strong>{{ $selectedPayment->gateway === 'wechat' ? '微信支付' : '支付宝' }}</strong></div><div><span class="muted small">当前状态</span><strong>{{ ['pending'=>'待支付','succeeded'=>'支付成功','failed'=>'未完成','cancelled'=>'已关闭'][$selectedPayment->status] ?? $selectedPayment->status }}</strong></div></div>
            @if (data_get($selectedPayment->payload,'retry_of_no'))<p class="small muted" style="margin-top:16px">重新支付记录，关联原单 {{ data_get($selectedPayment->payload,'retry_of_no') }}</p>@endif
            @if (data_get($selectedPayment->payload,'replacement_payment_id'))<p class="small muted" style="margin-top:16px">原订单已关闭，请使用 <a href="{{ route('console.recharge.index',['payment'=>data_get($selectedPayment->payload,'replacement_payment_id')]) }}">关联的新支付记录 {{ data_get($selectedPayment->payload,'replacement_payment_no') }}</a>。</p>
            @elseif (in_array($selectedPayment->status,['pending','failed','cancelled'],true))
                @if(!$qrCurrent)<p class="small muted" style="margin-top:16px">支付二维码未取得或已过期。继续支付前会核对原订单是否已付款；需要换单时先关闭原单。</p>@endif
                @if(!$qrCurrent)<form method="POST" action="{{ route('console.recharge.resume',$selectedPayment) }}" style="margin-top:16px">@csrf<button class="btn btn-primary" type="submit" data-resume-payment>继续支付 / 重新生成二维码</button></form>@endif
            @endif</div>
        </section>
    @elseif ($pendingPayment && ! $qrCurrent)
        <div class="note info" style="margin-bottom:18px">当前充值单暂未取得有效二维码，<a href="{{ route('console.recharge.index',['payment'=>$pendingPayment->id]) }}">打开原充值记录继续支付</a>。</div>
    @endif

    @if ($alipayQr !== '' && $pendingPayment)
        <div class="panel">
            <div class="panel-head">
                <h2>{{ $pendingPayment->gateway === 'wechat' ? '微信扫码支付' : '支付宝扫码支付' }}</h2>
                <span class="meta">支付单 {{ $pendingPayment->payment_no }}</span>
            </div>
            <div class="panel-body">
                <div style="display:flex;gap:28px;align-items:center;flex-wrap:wrap">
                    <div style="padding:14px;border:1px solid #e3eaf4;border-radius:14px;background:#fff">
                        <div id="alipay-qr" style="width:220px;height:220px"></div>
                    </div>
                    <div style="min-width:220px">
                        <div class="muted small">应付金额</div>
                        <div style="font-size:30px;font-weight:800;color:#0b1638;margin:4px 0 14px">{{ \App\Support\Money::format($pendingPayment->amount) }}</div>
                        <div class="muted small">请使用{{ $pendingPayment->gateway === 'wechat' ? '微信' : '支付宝' }}扫描左侧二维码完成支付。</div>
                        <div id="alipay-pay-status" class="note info" style="margin-top:14px">等待支付，支付成功后会自动刷新余额。</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="panel {{ $alipayQr !== '' ? 'mt-4' : '' }}">
        <div class="panel-head"><h2>创建充值</h2></div>
        <div class="panel-body">
            @if ($alipayEnabled || $wechatEnabled)
                <form method="POST" action="{{ route('console.recharge.store') }}" class="filter-bar" style="align-items:flex-end">
                    @csrf
                    <div class="form-field">
                        <label class="form-label">充值金额</label>
                        <input class="input" type="number" name="amount" min="1" max="50000" step="0.01" value="{{ old('amount') }}" placeholder="请输入充值金额" required style="width:200px">
                    </div>
                    <div class="form-field">
                        <label class="form-label">支付方式</label>
                        <select class="select" name="gateway" style="width:180px">
                            @if ($alipayEnabled)<option value="alipay" @selected(old('gateway') === 'alipay')>支付宝扫码</option>@endif
                            @if ($wechatEnabled)
                                <option value="wechat" @selected(old('gateway') === 'wechat')>微信支付</option>
                            @endif
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">生成支付二维码</button>
                </form>
                <div class="note info" style="margin-top:10px">支付二维码 15 分钟内有效，请使用所选支付渠道扫码支付。</div>
            @else
                <div class="note warn">
                    在线支付暂未配置，请联系管理员检查支付通道。
                </div>
            @endif
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>最近充值记录</h2><span class="meta">{{ $payments->count() }} 条</span></div>
        <div class="panel-body">
            @forelse ($payments as $payment)
                <div class="flex items-center justify-between gap-3" style="padding:10px 0;border-bottom:1px solid #f1f5f9">
                    <div>
                        <a class="strong small" href="{{ route('console.recharge.index',['payment'=>$payment->id]) }}">{{ $payment->payment_no }}</a>
                        <div class="small muted">{{ ['alipay' => '支付宝', 'wechat' => '微信支付', 'alipay_sim' => '支付宝（演示）', 'wechat_sim' => '微信支付（演示）', 'manual' => '对公转账'][$payment->gateway] ?? $payment->gateway }} · {{ $payment->created_at?->format('Y-m-d H:i') }}</div>
                    </div>
                    <div style="text-align:right">
                        <div class="strong">{{ \App\Support\Money::format($payment->amount) }}</div>
                        <x-status :status="$payment->status" :label="$payment->status === 'pending' ? '待支付' : null" />
                        <div class="recharge-record-actions">@if(data_get($payment->payload,'replacement_payment_id'))<a class="btn btn-ghost btn-sm" href="{{ route('console.recharge.index',['payment'=>data_get($payment->payload,'replacement_payment_id')]) }}">查看新支付单</a>@elseif(in_array($payment->status,['pending','failed','cancelled'],true)&&in_array($payment->gateway,['wechat','alipay'],true))<a class="btn btn-soft btn-sm" href="{{ route('console.recharge.index',['payment'=>$payment->id]) }}">继续支付</a>@else<a class="btn btn-ghost btn-sm" href="{{ route('console.recharge.index',['payment'=>$payment->id]) }}">查看详情</a>@endif</div>
                    </div>
                </div>
            @empty
                <div class="empty-state" style="padding:24px 0">
                    <div class="empty-icon"><x-icon name="wallet" :size="21" /></div>
                    <h3>暂无充值记录</h3>
                    <p>创建扫码充值单后，记录会显示在这里。</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
    @if ($alipayQr !== '' && $pendingPayment)
        <script src="{{ asset('assets/js/qrcode.min.js') }}"></script>
        <script nonce="{{ $cspNonce }}">
            (() => {
                const target = document.getElementById('alipay-qr');
                if (!target) {
                    return;
                }

                new QRCode(target, {
                    text: @json($alipayQr),
                    width: 220,
                    height: 220,
                    correctLevel: QRCode.CorrectLevel.M
                });

                const statusBox = document.getElementById('alipay-pay-status');
                const endpoint = @json(route('console.recharge.status', $pendingPayment));
                let stopped = false;
                const endAt = @json(data_get($pendingPayment->payload,'expires_at'));
                const deadline = endAt ? Date.parse(endAt) : Date.now() + 15 * 60000;

                const poll = () => {
                    fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then((response) => response.json())
                        .then((data) => {
                            if (['cancelled','failed'].includes(data.status)) { stopped = true; statusBox.textContent = '支付已结束，请从充值记录重新打开付款。'; return; }
                            if (data.status === 'succeeded') {
                                stopped = true;
                                statusBox.textContent = '支付成功，余额已到账，正在刷新…';
                                statusBox.className = 'note success';
                                window.setTimeout(() => window.location.reload(), 1200);
                            }
                        })
                        .catch(() => {});
                };

                poll();
                const timer = window.setInterval(() => {
                    if (Date.now() > deadline && !stopped) { stopped = true; statusBox.textContent = '二维码已过期，请刷新并从原充值记录重新支付。'; }
                    if (stopped) {
                        window.clearInterval(timer);
                        return;
                    }
                    poll();
                }, 3000);
            })();
        </script>
    @endif
@endpush

@pushOnce('styles','recharge-record-style')
<style>.recharge-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.recharge-detail-grid>div{display:grid;gap:9px}.recharge-detail-grid strong{font-size:16px}.recharge-record-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:9px}@media(max-width:600px){.recharge-detail-grid{grid-template-columns:minmax(0,1fr);gap:15px}[data-recharge-detail] .panel-head{flex-wrap:wrap;gap:8px}}</style>
@endPushOnce
@pushOnce('scripts','recharge-resume-button')
<script nonce="{{ $cspNonce??'' }}">document.querySelectorAll('form:has([data-resume-payment])').forEach(form=>form.addEventListener('submit',()=>{const b=form.querySelector('[data-resume-payment]');b.disabled=true;b.textContent='正在核对支付状态…';}));</script>
@endPushOnce
