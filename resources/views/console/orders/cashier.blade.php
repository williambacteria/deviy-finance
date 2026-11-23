@php
    $balanceEnough = \App\Support\Money::compare(auth()->user()->balance,$order->total) >= 0;
    $defaultGateway = $cashier['gateway'] ?: ($balanceEnough ? 'balance' : ($wechatEnabled ? 'wechat' : ($alipayEnabled ? 'alipay' : 'balance')));
    $cashierConfig = ['start'=>route('console.orders.cashier',$order),'status'=>route('console.orders.cashier.status',$order),'close'=>route('console.orders.cashier.close',$order),'autoOpen'=>request()->boolean('cashier'),'initial'=>$cashier];
@endphp
@pushOnce('styles','order-cashier-style')
<link rel="stylesheet" href="{{ asset('assets/css/order-cashier.css') }}?v={{ filemtime(public_path('assets/css/order-cashier.css')) }}">
@endPushOnce
@pushOnce('scripts','order-cashier-script')
<script src="{{ asset('assets/js/qrcode.min.js') }}"></script>
<script src="{{ asset('assets/js/order-cashier.js') }}?v={{ filemtime(public_path('assets/js/order-cashier.js')) }}" defer></script>
@endPushOnce
<dialog class="order-cashier-dialog" id="order-cashier" aria-labelledby="order-cashier-title">
    <header class="oc-header"><div><span>DEVIY CLOUD · 收银台</span><h2 id="order-cashier-title">选择支付方式</h2></div><button type="button" class="oc-close" data-close-order-cashier aria-label="关闭收银台"><x-icon name="x" :size="20" /></button></header>
    <div class="oc-body"><div class="oc-amount"><small>订单待支付</small><strong>{{ \App\Support\Money::format($order->total) }}</strong><span>{{ $order->order_no }}</span></div>
        <div role="radiogroup" aria-label="支付方式" class="oc-methods">
            @foreach(['alipay'=>['支付宝','支付宝 App 扫码',$alipayEnabled,'支'],'wechat'=>['微信支付','微信扫一扫付款',$wechatEnabled,'微'],'balance'=>['余额支付','可用余额 '.\App\Support\Money::format(auth()->user()->balance),$balanceEnough,'余']] as $key=>$method)
            <label class="oc-method {{ !$method[2] ? 'is-disabled' : '' }}" data-cashier-method="{{ $key }}"><input type="radio" name="cashier_gateway" value="{{ $key }}" @checked($defaultGateway===$key) @disabled(!$method[2])><span class="oc-method-icon oc-method-icon-{{ $key }}">{{ $method[3] }}</span><span class="oc-method-copy"><b>{{ $method[0] }}</b><small>{{ $method[2] ? $method[1] : ($key==='balance'?'余额不足，请选择扫码支付':'暂未开通') }}</small></span><span class="oc-radio" aria-hidden="true"></span></label>
            @endforeach
        </div>
        <div class="oc-error" role="alert" data-cashier-error hidden></div>
        <div class="oc-qr-panel" data-cashier-qr-panel hidden><div class="oc-qr-box"><div data-order-qr></div></div><div class="oc-qr-copy"><b data-cashier-scan-label>请使用微信扫码</b><small data-cashier-payment-number></small><span role="status" data-cashier-status>等待付款，支付成功后自动更新。</span><button class="oc-text-button" type="button" data-cashier-restart>关闭二维码并更换支付方式</button></div></div>
        <div class="oc-success" data-cashier-success hidden><span><x-icon name="check" :size="25" /></span><h3>支付成功</h3><p>订单已进入交付流程，请在我的资源中查看进度。</p><a class="btn btn-primary" href="{{ route('console.services.index') }}">查看我的资源</a> <a class="btn btn-ghost" href="{{ route('console.orders.show',$order) }}">查看订单详情</a></div>
        <button class="btn btn-primary oc-pay" type="button" data-cashier-pay>确认支付 {{ \App\Support\Money::format($order->total) }}</button>
        <p class="oc-footnote"><x-icon name="lock" :size="13" /> 扫码支付直接结算本订单，无需先充值。</p>
    </div>
</dialog>
<script type="application/json" id="order-cashier-config" nonce="{{ $cspNonce??'' }}">@json($cashierConfig)</script>
