@php
    $paymentChannel = request()->query('channel') === 'wechat' || str_starts_with((string) session('status'), '微信支付') || ($errors->any() && old('mch_id') !== null) ? 'wechat' : 'alipay';
    $wechatOn = (bool) ($wechat['enabled'] ?? false);
    $activeChannels = (int) $enabled + (int) $wechatOn;
    $aliInput = fn ($key, $value) => $paymentChannel === 'alipay' ? old($key, $value) : $value;
@endphp
@pushOnce('styles','payment-channel-design')
<link rel="stylesheet" href="{{ asset('assets/css/payment-channels.css') }}?v={{ filemtime(public_path('assets/css/payment-channels.css')) }}">
@endPushOnce
@pushOnce('scripts','payment-channel-controls')
<script src="{{ asset('assets/js/payment-channels.js') }}?v={{ filemtime(public_path('assets/js/payment-channels.js')) }}" defer></script>
@endPushOnce
<div class="payment-center" data-payment-center>
    <header class="pc-intro"><div><span class="pc-eyebrow">PAYMENT CHANNELS</span><h2>支付渠道</h2><p>管理收款方式、商户凭据与支付通知。</p></div><span class="pc-count"><i></i>{{ $activeChannels }} / 2 个渠道已开启</span></header>
    <nav class="pc-channel-grid" role="tablist" aria-label="选择支付渠道">
        <a class="pc-channel {{ $paymentChannel === 'alipay' ? 'is-selected' : '' }}" href="{{ route('admin.settings.index',['section'=>'payment','channel'=>'alipay']) }}" role="tab" aria-selected="{{ $paymentChannel === 'alipay' ? 'true' : 'false' }}" aria-controls="pc-alipay" id="pc-tab-alipay" data-channel="alipay" tabindex="{{ $paymentChannel === 'alipay' ? '0' : '-1' }}">
            <span class="pc-brand pc-brand-alipay" aria-hidden="true">支</span><span class="pc-channel-copy"><strong>支付宝</strong><small>电脑网站 · 扫码充值</small></span><span class="pc-state {{ $enabled ? 'is-on' : '' }}">{{ $enabled ? '已开启' : '未开启' }}</span><span class="pc-selected-mark" aria-hidden="true"><x-icon name="check" :size="12" /></span>
        </a>
        <a class="pc-channel {{ $paymentChannel === 'wechat' ? 'is-selected' : '' }}" href="{{ route('admin.settings.index',['section'=>'payment','channel'=>'wechat']) }}" role="tab" aria-selected="{{ $paymentChannel === 'wechat' ? 'true' : 'false' }}" aria-controls="pc-wechat" id="pc-tab-wechat" data-channel="wechat" tabindex="{{ $paymentChannel === 'wechat' ? '0' : '-1' }}">
            <span class="pc-brand pc-brand-wechat" aria-hidden="true"><svg width="27" height="27" viewBox="0 0 28 28" fill="none"><path d="M17.8 15.9c0 4.5-5.1 7.1-8.7 5.1l-3.7 1 .9-3.2C1.8 15.7 4.3 9 10.7 9c4 0 7.1 3 7.1 6.9Z" fill="currentColor"/><path d="M14.5 10.2c0-3.7 4.1-5.8 7.2-4.2l3-.9-.7 2.7c3.7 2.5 1.7 8-3.6 8-3.3 0-5.9-2.4-5.9-5.6Z" fill="currentColor" opacity=".75"/><circle cx="8.2" cy="14.4" r=".85" fill="white"/><circle cx="12.7" cy="14.4" r=".85" fill="white"/></svg></span><span class="pc-channel-copy"><strong>微信支付</strong><small>Native · 扫码充值</small></span><span class="pc-state {{ $wechatOn ? 'is-on' : '' }}">{{ $wechatOn ? '已开启' : '未开启' }}</span><span class="pc-selected-mark" aria-hidden="true"><x-icon name="check" :size="12" /></span>
        </a>
    </nav>
    <div class="pc-channel-panel" id="pc-alipay" role="tabpanel" aria-labelledby="pc-tab-alipay" @if($paymentChannel !== 'alipay') hidden @endif data-channel-panel="alipay">
        <form method="POST" action="{{ route('admin.payments.update') }}" data-payment-form="alipay">
            @csrf @method('PUT')
            <header class="pc-config-head"><div><h3>支付宝配置</h3><p>商户应用与网站支付接入信息</p></div><label class="pc-toggle"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked((bool)$aliInput('enabled',$enabled))><span class="pc-switch" aria-hidden="true"></span><span>启用渠道</span></label></header>
            <div class="pc-config-body">
                <section class="pc-section"><div class="pc-section-heading"><span class="pc-section-icon"><x-icon name="card" :size="18" /></span><div><h4>商户信息</h4><p>用于创建支付宝充值交易。</p></div></div><div class="pc-fields">
                    <label class="pc-field"><span>支付宝 AppID</span><input class="input" name="app_id" value="{{ $aliInput('app_id',$appId) }}" placeholder="填写支付宝应用 AppID"><small>支付宝开放平台中已开通支付的应用。</small></label>
                    <label class="pc-field"><span>商户私钥 <em class="pc-field-tag">{{ $privateConfigured ? '已配置' : '待配置' }}</em></span><input class="input" type="password" name="private_key" value="" autocomplete="new-password" placeholder="{{ $privateConfigured ? '留空保留原私钥' : '粘贴商户应用私钥' }}"><small>加密保存，不回显；重新填写时替换。</small></label>
                </div></section>
                <section class="pc-section"><div class="pc-section-heading"><span class="pc-section-icon"><x-icon name="shield" :size="18" /></span><div><h4>支付验签</h4><p>使用支付宝公钥验证支付结果。</p></div></div><label class="pc-field"><span>支付宝公钥 <em class="pc-field-tag">{{ $publicKey !== '' ? '已配置' : '待配置' }}</em></span><textarea class="input pc-code" name="public_key" rows="4" spellcheck="false" placeholder="粘贴支付宝公钥">{{ $aliInput('public_key',$publicKey) }}</textarea><small>公钥可回显；已有配置时，留空保留原值。</small></label></section>
                <section class="pc-section"><div class="pc-section-heading"><span class="pc-section-icon"><x-icon name="network" :size="18" /></span><div><h4>通知与网关</h4><p>支付结果回调与页面返回地址。</p></div></div><div class="pc-fields">
                    <label class="pc-field"><span>异步通知地址</span><input class="input" type="url" name="notify_url" value="{{ $aliInput('notify_url',$notifyUrl) }}" placeholder="https://www.deviy.cn/payments/alipay/notify"><small>接收支付完成通知并更新充值状态。</small></label>
                    <label class="pc-field"><span>同步返回地址</span><input class="input" type="url" name="return_url" value="{{ $aliInput('return_url',$returnUrl) }}" placeholder="https://www.deviy.cn/payments/alipay/return"><small>用户付款后返回本站的地址。</small></label>
                </div><label class="pc-field pc-field-spaced"><span>支付宝网关</span><input class="input" type="url" name="gateway" value="{{ $aliInput('gateway',$gateway) }}" placeholder="https://openapi.alipay.com/gateway.do"><small>生产环境：https://openapi.alipay.com/gateway.do</small></label></section>
            </div>
            <footer class="pc-save-bar"><span><x-icon name="lock" :size="15" /> <span data-unsaved-note>商户私钥加密保存</span></span><button class="btn btn-primary" type="submit">保存支付宝配置</button></footer>
        </form>
    </div>
    <div class="pc-channel-panel" id="pc-wechat" role="tabpanel" aria-labelledby="pc-tab-wechat" @if($paymentChannel !== 'wechat') hidden @endif data-channel-panel="wechat">
        @include('admin.payments.wechat')
    </div>
    <div class="pc-bottom-note"><x-icon name="shield" :size="17" /><p>两个渠道独立保存。密钥留空不会清除原配置；首次启用或更换凭据后，请完成小额支付联调。</p></div>
</div>
