@extends('layouts.site')

@section('title', '确认订单 · '.$product->name)

@section('content')
    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a> / 确认订单</div>
            <h1>确认订单</h1>
            <p>核对配置与周期后提交。订单创建后可在控制台使用余额支付。</p>
        </div>
    </section>

    <section class="section" style="padding-top:18px">
        <div class="container public-grid">
            <div class="light-panel">
                <h2 style="margin:0 0 18px;font-size:21px">{{ $product->name }}</h2>

                <form method="POST" action="{{ route('checkout.store', $product) }}" id="checkout-form">
                    @csrf
                    @if (\App\Support\ServicePresentation::kind($product) === 'cdn')
                        @foreach (\App\Support\ServicePresentation::cdnFields($product) as $label => $value)
                            <div class="flex justify-between" style="font-size:13px;padding:6px 0"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                        @endforeach
                    @else
                    @foreach ($selectedOptions as $key => $option)
                        <input type="hidden" name="options[{{ $key }}]" value="{{ $option['value'] }}">
                    @endforeach
                    @endif
                    @if (\App\Support\ServicePresentation::kind($product) !== 'cdn' && request('upstream_os'))
                        <input type="hidden" name="upstream_os" value="{{ request('upstream_os') }}">
                    @endif
                    @foreach ((array) request('upstream_config', []) as $configId => $configValue)
                        <input type="hidden" name="upstream_config[{{ $configId }}]" value="{{ $configValue }}">
                    @endforeach

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-label">地域</label>
                            <input class="input" type="text" value="{{ $product->region }}" disabled>
                            <input type="hidden" name="region" value="{{ $product->region }}">
                        </div>
                        <div class="form-field">
                            <label class="form-label">数量</label>
                            <input class="input" id="quantity" type="number" name="quantity" min="1" max="10" value="{{ old('quantity', $selectedQuantity ?? 1) }}" required>
                        </div>
                    </div>

                    <div class="form-field mt-4">
                        <label class="form-label">计费周期 <span>*</span></label>
                        <div class="price-choice">
                            @foreach ($product->prices as $price)
                                <label>
                                    @php($cycleDiscount = (float) ($cycleDiscounts[$price->billing_cycle] ?? 1))
                                    <span><input type="radio" name="billing_cycle" value="{{ $price->billing_cycle }}" data-price="{{ $price->price }}" @checked($selectedCycle === $price->billing_cycle)> {{ $cycles[$price->billing_cycle]['label'] ?? $price->billing_cycle }}@if ($cycleDiscount < 1)<span class="cycle-discount">{{ rtrim(rtrim(number_format($cycleDiscount * 10, 1), '0'), '.') }}折</span>@endif</span>
                                    <b data-cycle-price="{{ $price->billing_cycle }}">{{ \App\Support\Money::format($price->price) }}</b>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="note info mt-4">
                        提交订单后将生成待支付账单，请在 3 天内完成支付。演示环境支持余额支付，生产环境可接入支付宝 / 微信。
                    </div>

                    <button class="btn btn-primary btn-lg btn-block mt-4" type="submit">提交订单</button>
                </form>
            </div>

            <aside class="light-panel">
                <div class="small" style="color:var(--dt-t-64748b,#64748b);font-weight:700;letter-spacing:1px">ORDER SUMMARY</div>
                <h2 style="margin:12px 0 0;font-size:18px">{{ $product->name }}</h2>
                <div class="small muted" style="margin-top:4px">{{ \App\Support\ServicePresentation::kind($product) === 'cdn' ? 'CDN 加速服务' : ($product->region.' · '.($product->type === 'bare_metal' ? '裸金属服务器' : ($product->type === 'nat_vm' ? 'NAT 云主机' : '云服务器'))) }}</div>

                <div class="mt-4" style="border-top:1px solid var(--dt-e-edf2f7,#edf2f7);padding-top:14px">
                    @if (\App\Support\ServicePresentation::kind($product) === 'cdn')
                        @foreach (\App\Support\ServicePresentation::cdnFields($product) as $label => $value)
                            <div class="flex justify-between" style="font-size:13px;padding:6px 0;gap:16px"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                        @endforeach
                    @else
                    @foreach ($selectedOptions as $key => $option)
                        <div class="flex justify-between" style="font-size:12px;padding:5px 0;color:var(--dt-t-64748b,#64748b)">
                            <span>{{ \App\Support\ProductOptions::DIMENSIONS[$key]['label'] }}</span>
                            <b style="color:var(--dt-t-0f172a,#0f172a)">{{ $option['label'] }}</b>
                        </div>
                    @endforeach
                    @if (request('upstream_os'))
                        @php($upstreamOs = explode('|', (string) request('upstream_os'), 2))
                        <div class="flex justify-between" style="font-size:12px;padding:5px 0;color:var(--dt-t-64748b,#64748b)">
                            <span>操作系统</span>
                            <b style="color:var(--dt-t-0f172a,#0f172a)">{{ trim($upstreamOs[0] ?? '') }}{{ isset($upstreamOs[1]) ? ' · '.trim($upstreamOs[1]) : '' }}</b>
                        </div>
                    @endif
                    @endif
                    <div class="flex justify-between" style="font-size:12px;padding:6px 0;color:var(--dt-t-64748b,#64748b)"><span>计费周期</span><b id="summary-cycle" style="color:var(--dt-t-0f172a,#0f172a)">—</b></div>
                    <div class="flex justify-between" style="font-size:12px;padding:6px 0;color:var(--dt-t-64748b,#64748b)"><span>数量</span><b id="summary-qty" style="color:var(--dt-t-0f172a,#0f172a)">1</b></div>
                    <div class="flex justify-between" style="font-size:12px;padding:6px 0;color:var(--dt-t-64748b,#64748b)"><span>账户余额</span><b style="color:var(--dt-t-0f172a,#0f172a)">{{ \App\Support\Money::format(auth()->user()->balance) }}</b></div>
                </div>

                <div class="flex justify-between items-center" style="border-top:1px solid var(--dt-e-edf2f7,#edf2f7);margin-top:12px;padding-top:14px">
                    <span class="small muted">预计合计</span>
                    <b id="summary-total" style="font-size:28px;letter-spacing:-.5px">—</b>
                </div>
                <div class="auth-hint" style="margin-top:14px">提交后可在订单详情页完成支付，余额不足时请先充值。</div>

                @if (($availability['status'] ?? 'ok') !== 'ok')
                    <div class="note {{ $availability['status'] === 'failed' ? 'warn' : '' }}" style="margin-top:14px">
                        <b>{{ $availability['status'] === 'failed' ? '当前不可购买' : '开通提示' }}</b>
                        <div class="small" style="margin-top:6px">
                            @foreach (array_merge($availability['errors'], $availability['warnings']) as $message)
                                <div>· {{ $message }}</div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="note info" style="margin-top:14px">节点、库存与容量检查通过，可正常下单。</div>
                @endif
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        const prices = {{ \Illuminate\Support\Js::from($product->prices->mapWithKeys(fn ($price) => [$price->billing_cycle => (float) $price->price])) }};
        const labels = {{ \Illuminate\Support\Js::from(collect($cycles)->map(fn ($cycle) => $cycle['label'])) }};
        const months = {{ \Illuminate\Support\Js::from(collect($cycles)->map(fn ($cycle) => $cycle['months'])) }};
        const cycleDiscounts = {{ \Illuminate\Support\Js::from($cycleDiscounts) }};
        const monthlyAddon = {{ (float) $monthlyAddon }};
        const form = document.getElementById('checkout-form');
        const quantity = document.getElementById('quantity');
        const cycle = () => form.querySelector('input[name="billing_cycle"]:checked');
        const money = (value) => '¥' + Number(value).toFixed(2);

        function refreshSummary() {
            const selected = cycle();
            const qty = Math.max(1, parseInt(quantity.value || '1', 10));
            const cycleValue = selected ? selected.value : 'monthly';
            const price = (prices[cycleValue] || 0) + monthlyAddon * (months[cycleValue] || 1) * (cycleDiscounts[cycleValue] || 1);
            document.getElementById('summary-cycle').textContent = labels[cycleValue] || cycleValue;
            document.getElementById('summary-qty').textContent = qty;
            document.getElementById('summary-total').textContent = money(price * qty);
            document.querySelectorAll('[data-cycle-price]').forEach(function (item) {
                const value = item.dataset.cyclePrice;
                const cyclePrice = (prices[value] || 0) + monthlyAddon * (months[value] || 1) * (cycleDiscounts[value] || 1);
                item.textContent = money(cyclePrice);
            });
        }

        form.querySelectorAll('input[name="billing_cycle"]').forEach((input) => input.addEventListener('change', refreshSummary));
        quantity.addEventListener('input', refreshSummary);
        refreshSummary();
    </script>
@endpush
