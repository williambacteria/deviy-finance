@extends('layouts.site')

@section('title', $product->name)

@section('content')
    @php
        $optionRows = \App\Support\ProductOptions::options($product);
        $optionSettings = \App\Support\ProductOptions::settings($product);
        $initialConfig = \App\Support\ProductOptions::resolve($product, []);
        $isNatProduct = $product->type === 'nat_vm' || data_get($product->metadata, 'controller.network_mode') === 'private';
        $initialSelections = $initialConfig['selection'];
        $defaultAddon = $initialConfig['monthly_addon'];
        $firstPrice = $product->prices->first();
        $firstMonths = $firstPrice ? \App\Support\Billing::months($firstPrice->billing_cycle) : 1;
        $cycleDiscounts = \App\Support\ProductOptions::cycleDiscounts($product);
        $osFamilies = [];
        foreach ($optionRows['ostype'] as $osRow) {
            [$familyKey, $familyLabel] = \App\Support\ProductOptions::osFamily((string) $osRow['value'], (string) $osRow['label']);
            $osFamilies[$familyKey]['label'] = $familyLabel;
            $osFamilies[$familyKey]['options'][] = $osRow;
        }
        $selectedOs = $initialSelections['ostype'];
        $selectedOsFamily = \App\Support\ProductOptions::osFamily((string) $selectedOs['value'], (string) $selectedOs['label'])[0];
        $firstDiscount = $firstPrice ? ($cycleDiscounts[$firstPrice->billing_cycle] ?? 1) : 1;
        $initialTotal = ($firstPrice?->price ?? 0) + $defaultAddon * $firstMonths * $firstDiscount;
        $details = is_array($product->metadata['details'] ?? null) ? $product->metadata['details'] : [];
        $upstreamPurchaseUrl = trim((string) data_get($product->metadata, 'upstream_purchase_url', ''));
        $upstreamSpecs = is_array(data_get($product->metadata, 'upstream_specs')) ? data_get($product->metadata, 'upstream_specs') : [];
        $upstreamOsGroups = is_array(data_get($product->metadata, 'upstream_os_groups')) ? data_get($product->metadata, 'upstream_os_groups') : [];
        $upstreamConfigGroups = is_array(data_get($product->metadata, 'upstream_config_groups')) ? data_get($product->metadata, 'upstream_config_groups') : [];
        $isCdnProduct = \App\Support\ServicePresentation::kind($product) === 'cdn';
        $isMofangProduct = $product->upstream_source === 'mofang' || data_get($product->metadata, 'upstream') === 'mofang';
        $upstreamMarkup = 1 + ((float) data_get($product->metadata, 'markup_percent', 0) / 100);
    @endphp

    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb"><a href="{{ route('home') }}">首页</a> / <a href="{{ route('products.index') }}">产品选购</a> / {{ $product->name }}</div>
            <h1>{{ $product->name }}</h1>
        </div>
    </section>

    <section class="section" style="padding-top:18px">
        <div class="container">
            <form method="GET" action="{{ route('checkout.show', $product) }}" id="purchase-form">
                <div class="public-grid">
                    <div class="light-panel">
                        @if ($isMofangProduct)
                            <h2 style="margin:0 0 8px;font-size:22px">{{ $product->group?->name ?: '配置与说明' }}</h2>
                            @if ($product->group?->description)
                                <div class="product-description">{!! nl2br(e($product->group->description)) !!}</div>
                            @endif
                        @else
                            <h2 style="margin:0 0 8px;font-size:22px">配置与说明</h2>
                            @if ($product->description || $product->short_description)
                                <div class="product-description">{!! nl2br(e($product->description ?: $product->short_description)) !!}</div>
                            @endif
                            @if ($upstreamSpecs !== [])
                                <dl class="product-detail-specs">
                                    @foreach ($upstreamSpecs as $upstreamSpec)
                                        <div><dt>{{ $upstreamSpec['label'] ?? '' }}</dt><dd>{{ $upstreamSpec['value'] ?? '' }}</dd></div>
                                    @endforeach
                                </dl>
                            @endif
                        @endif

                        @if ($isCdnProduct)
                            <dl class="product-detail-specs" data-cdn-package>
                                @forelse (\App\Support\ServicePresentation::cdnFields($product) as $label => $value)
                                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @empty
                                    <div><dt>产品类型</dt><dd>CDN 加速服务</dd></div>
                                @endforelse
                            </dl>
                        @endif
                        @if ($isMofangProduct && $upstreamConfigGroups !== [])
                            <div class="config-options">
                                @foreach ($upstreamConfigGroups as $configGroup)
                                    @php($isOsGroup = str_contains((string) ($configGroup['label'] ?? ''), '操作系统'))
                                    @if ($isCdnProduct && $isOsGroup) @continue @endif
                                    <div class="config-group" data-upstream-config-group="{{ $configGroup['id'] ?? '' }}">
                                        <div class="config-label"><span>{{ $configGroup['label'] ?? '' }}</span></div>
                                        @if ($isOsGroup && $upstreamOsGroups !== [])
                                            <div class="upstream-os-grid">
                                                @foreach ($upstreamOsGroups as $osIndex => $osGroup)
                                                    <div class="upstream-os-card {{ $osIndex === 0 ? 'active' : '' }}" data-upstream-os-family>
                                                        <button class="upstream-os-family" type="button" data-upstream-os-trigger>
                                                            @if (! empty($osGroup['icon']))
                                                                <img src="{{ $osGroup['icon'] }}" alt="{{ $osGroup['family'] ?? '' }}">
                                                            @endif
                                                            <span>{{ $osGroup['family'] ?? '' }}</span>
                                                        </button>
                                                        <select class="select upstream-os-version" data-upstream-os-version data-group-id="{{ $configGroup['id'] ?? '' }}">
                                                            @foreach (($osGroup['versions'] ?? []) as $version)
                                                                <option value="{{ $version['value'] ?? '' }}">{{ $version['label'] ?? '' }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <input type="hidden" name="upstream_config[{{ $configGroup['id'] ?? '' }}]" value="{{ data_get($upstreamOsGroups, '0.versions.0.value', '') }}" data-upstream-input="{{ $configGroup['id'] ?? '' }}">
                                        @else
                                            <div class="config-pills">
                                                @foreach (($configGroup['options'] ?? []) as $optionIndex => $configOption)
                                                    @php($optionPrice = round(((float) ($configOption['price'] ?? 0)) * $upstreamMarkup, 2))
                                                    <button class="config-pill {{ $optionIndex === 0 ? 'active' : '' }}" type="button"
                                                        data-upstream-choice
                                                        data-group-id="{{ $configGroup['id'] ?? '' }}"
                                                        data-value="{{ $configOption['value'] ?? '' }}"
                                                        data-price="{{ $optionPrice }}">
                                                        {{ $configOption['label'] ?? '' }}
                                                        @if ($optionPrice > 0)
                                                            <small>+¥{{ number_format($optionPrice, 2) }}/月</small>
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                            <input type="hidden" name="upstream_config[{{ $configGroup['id'] ?? '' }}]" value="{{ data_get($configGroup, 'options.0.value', '') }}" data-upstream-input="{{ $configGroup['id'] ?? '' }}">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif (! $isCdnProduct)
                        <div class="config-options">
                            @foreach ($optionRows as $key => $rows)
                                @if ($key === 'nat_ports' && ! $isNatProduct) @continue @endif
                                @if ($rows === []) @continue @endif
                                @php($setting = $optionSettings[$key])
                                @php($selectedOption = $initialSelections[$key])
                                <div class="config-group" data-option-group="{{ $key }}">
                                    <div class="config-label">
                                        <span>{{ \App\Support\ProductOptions::DIMENSIONS[$key]['label'] }}</span>
                                        @if ($setting['hint'] !== '')
                                            <small>{{ $setting['hint'] }}</small>
                                        @endif
                                    </div>
                                    @if ($key === 'ostype')
                                        <div class="os-selector" data-os-selector>
                                            <div class="os-family-grid">
                                                @foreach ($osFamilies as $familyKey => $family)
                                                    <button class="os-family-card {{ $selectedOsFamily === $familyKey ? 'active' : '' }}" type="button" data-os-family="{{ $familyKey }}">
                                                        <span class="os-family-icon os-{{ $familyKey }}">
                                                            @if ($familyKey === 'windows') ▦
                                                            @elseif ($familyKey === 'debian') ◉
                                                            @elseif ($familyKey === 'ubuntu') ◌
                                                            @elseif ($familyKey === 'centos') ✳
                                                            @elseif ($familyKey === 'rocky') ✦
                                                            @else ⌘
                                                            @endif
                                                        </span>
                                                        <span class="os-family-name">{{ $family['label'] }}</span>
                                                        <span class="os-check">✓</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                            <div class="os-version-row">
                                                <label>系统版本</label>
                                                <select class="select" data-os-version>
                                                    @foreach ($osFamilies[$selectedOsFamily]['options'] as $osRow)
                                                        <option value="{{ $osRow['value'] }}" data-price="{{ $osRow['price'] }}" @selected((string) $osRow['value'] === (string) $selectedOs['value'])>{{ $osRow['label'] }}{{ (float) $osRow['price'] > 0 ? ' (+¥'.number_format((float) $osRow['price'], 2).'/月)' : '' }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="hidden" name="options[{{ $key }}]" value="{{ $selectedOs['value'] }}" data-option-input="{{ $key }}">
                                            <input type="hidden" data-os-price value="{{ $selectedOs['price'] }}">
                                        </div>
                                    @elseif ($setting['range']['enabled'])
                                        <div class="config-range">
                                            <input type="range" min="{{ $setting['range']['min'] }}" max="{{ $setting['range']['max'] }}" step="{{ $setting['range']['step'] }}" value="{{ $selectedOption['value'] }}" data-range-input="{{ $key }}" data-range-unit-price="{{ $setting['range']['unit_price'] }}" data-range-unit="{{ \App\Support\ProductOptions::DIMENSIONS[$key]['unit'] }}">
                                            <div class="config-range-number-wrap">
                                                <input class="input config-range-number" type="number" min="{{ $setting['range']['min'] }}" max="{{ $setting['range']['max'] }}" step="{{ $setting['range']['step'] }}" value="{{ $selectedOption['value'] }}" data-range-number="{{ $key }}">
                                                <span>{{ \App\Support\ProductOptions::DIMENSIONS[$key]['unit'] }}</span>
                                            </div>
                                            <div class="config-range-meta">
                                                <b data-range-display="{{ $key }}">{{ $selectedOption['label'] }}</b>
                                                <small data-range-price="{{ $key }}">{{ (float) $selectedOption['price'] > 0 ? '+¥'.number_format((float) $selectedOption['price'], 2) : '已包含' }}</small>
                                            </div>
                                            <input type="hidden" name="options[{{ $key }}]" value="{{ $selectedOption['value'] }}" data-option-input="{{ $key }}">
                                        </div>
                                    @else
                                        <div class="config-pills">
                                            @foreach ($rows as $row)
                                                <button class="config-pill {{ (string) $row['value'] === (string) $selectedOption['value'] ? 'active' : '' }}" type="button"
                                                    data-option-key="{{ $key }}"
                                                    data-option-value="{{ $row['value'] }}"
                                                    data-option-price="{{ $row['price'] }}"
                                                    data-option-label="{{ $row['label'] }}">
                                                    {{ $row['label'] }}
                                                    @if ((float) $row['price'] > 0)
                                                        <small>+¥{{ number_format((float) $row['price'], 2) }}/月</small>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                        <input type="hidden" name="options[{{ $key }}]" value="{{ $selectedOption['value'] }}" data-option-input="{{ $key }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @endif

                        @if (! $isCdnProduct && $upstreamConfigGroups === [] && $upstreamOsGroups !== [])
                            <div class="config-group">
                                <div class="config-label"><span>操作系统</span></div>
                                <select class="select" name="upstream_os" required>
                                    @foreach ($upstreamOsGroups as $osGroup)
                                        <optgroup label="{{ $osGroup['family'] ?? '' }}">
                                            @foreach (($osGroup['versions'] ?? []) as $osVersion)
                                                <option value="{{ ($osGroup['family'] ?? '').'|'.($osVersion['value'] ?? '') }}">{{ $osGroup['family'] ?? '' }} - {{ $osVersion['label'] ?? '' }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <aside class="light-panel product-buy-aside">
                        <div class="small" style="color:var(--dt-t-2563eb,#2563eb);font-weight:700;letter-spacing:.5px">配置总价</div>
                        <div class="product-total">
                            <span class="product-total-currency">¥</span><span class="product-total-price" id="product-total">{{ number_format((float) $initialTotal, 2) }}</span>
                            <span class="small muted product-total-cycle" style="font-weight:400">/ <span id="product-cycle">{{ $firstPrice ? \App\Support\Billing::label($firstPrice->billing_cycle) : '' }}</span></span>
                        </div>
                        <div class="small muted" style="margin-top:4px">已包含所选配置的月付加价</div>

                        <div class="selected-config-list">
                            @if (! $isMofangProduct)
                            @if ((float) ($product->prices->firstWhere('billing_cycle', 'monthly')?->price ?? 0) > 0)
                                <div class="selected-config-item">
                                    <span>基础月付</span>
                                    <b>¥{{ number_format((float) ($product->prices->firstWhere('billing_cycle', 'monthly')?->price ?? 0), 2) }}</b>
                                    <small>基础</small>
                                </div>
                            @endif
                            @foreach ($initialSelections as $key => $option)
                                @if ($key === 'nat_ports' && ! $isNatProduct) @continue @endif
                                @if (($optionRows[$key] ?? []) === []) @continue @endif
                                <div class="selected-config-item" data-summary-row="{{ $key }}">
                                    <span>{{ \App\Support\ProductOptions::DIMENSIONS[$key]['label'] }}</span>
                                    <b data-summary-label="{{ $key }}">{{ $option['label'] }}</b>
                                    <small data-summary-price="{{ $key }}">{{ (float) $option['price'] > 0 ? '+¥'.number_format((float) $option['price'], 2) : '已包含' }}</small>
                                </div>
                            @endforeach
                            @endif
                        </div>

                        <div class="product-quantity">
                            <div class="product-quantity-label">购买数量</div>
                            <div class="product-quantity-control">
                                <button type="button" data-quantity-minus aria-label="减少数量">−</button>
                                <input type="number" name="quantity" id="product-quantity" value="{{ max(1, min(10, (int) request('quantity', 1))) }}" min="1" max="10" inputmode="numeric">
                                <button type="button" data-quantity-plus aria-label="增加数量">+</button>
                            </div>
                        </div>

                        <div class="price-choice">
                            @foreach ($product->prices as $price)
                                <label>
                                    @php($cycleDiscount = (float) ($cycleDiscounts[$price->billing_cycle] ?? 1))
                                    <span><input type="radio" name="billing_cycle" value="{{ $price->billing_cycle }}" data-price="{{ $price->price }}" @checked($loop->first)> {{ \App\Support\Billing::label($price->billing_cycle) }}@if ($cycleDiscount < 1)<span class="cycle-discount">{{ rtrim(rtrim(number_format($cycleDiscount * 10, 1), '0'), '.') }}折</span>@endif</span>
                                    <b data-cycle-price="{{ $price->billing_cycle }}">{{ \App\Support\Money::format($price->price) }}</b>
                                </label>
                            @endforeach
                        </div>

                        <button class="btn btn-primary btn-block btn-lg mt-4" type="submit">立即购买 <x-icon name="arrow-right" :size="16" /></button>
                        <a class="btn btn-ghost btn-block mt-2" href="{{ route('console.tickets.create') }}">购买前咨询</a>
                        <div class="auth-hint" style="margin-top:16px">支持余额支付；如需对公转账或发票，请联系客服。</div>
                    </aside>
                </div>
            </form>

            @if ($relatedProducts->count())
                <div class="mt-6" style="margin-top:38px">
                    <div class="section-head">
                        <div><div class="kicker">RELATED</div><h2 style="font-size:24px">你可能还需要</h2></div>
                    </div>
                    <div class="product-grid">
                        @foreach ($relatedProducts as $related)
                            @include('site.partials.product-card', ['product' => $related])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        const basePrices = {{ \Illuminate\Support\Js::from($product->prices->mapWithKeys(fn ($price) => [$price->billing_cycle => (float) $price->price])) }};
        const months = {{ \Illuminate\Support\Js::from(collect(\App\Support\Billing::cycles())->map(fn ($cycle) => $cycle['months'])) }};
        const cycleDiscounts = {{ \Illuminate\Support\Js::from(\App\Support\ProductOptions::cycleDiscounts($product)) }};
        const osFamilies = {{ \Illuminate\Support\Js::from($osFamilies) }};
        const cycleLabels = {{ \Illuminate\Support\Js::from(collect(\App\Support\Billing::cycles())->map(fn ($cycle) => $cycle['label'])) }};
        const form = document.getElementById('purchase-form');
        const quantityInput = document.getElementById('product-quantity');
        const quantityValue = () => Math.max(1, Math.min(10, parseInt(quantityInput?.value || '1', 10)));
        let monthlyAddon = {{ (float) $defaultAddon }};
        let upstreamAddon = 0;

        function selectedCycle() {
            return form.querySelector('input[name="billing_cycle"]:checked');
        }

        function refreshTotal() {
            const cycle = selectedCycle();
            const cycleValue = cycle ? cycle.value : 'monthly';
            const addon = monthlyAddon * (months[cycleValue] || 1) * (cycleDiscounts[cycleValue] || 1);
            const upstreamCycleAddon = upstreamAddon * (months[cycleValue] || 1) * (cycleDiscounts[cycleValue] || 1);
            const qty = quantityValue();
            const total = ((basePrices[cycleValue] || 0) + addon + upstreamCycleAddon) * qty;
            document.getElementById('product-total').textContent = Number(total).toFixed(2);
            document.getElementById('product-cycle').textContent = cycleLabels[cycleValue] || cycleValue;
            document.querySelectorAll('[data-cycle-price]').forEach(function (item) {
                const value = item.dataset.cyclePrice;
                const cycleAddon = monthlyAddon * (months[value] || 1) * (cycleDiscounts[value] || 1);
                const cycleUpstreamAddon = upstreamAddon * (months[value] || 1) * (cycleDiscounts[value] || 1);
                item.textContent = '¥' + Number(((basePrices[value] || 0) + cycleAddon + cycleUpstreamAddon) * quantityValue()).toFixed(2);
            });
        }

        function recalcUpstreamAddon() {
            upstreamAddon = 0;
            document.querySelectorAll('[data-upstream-choice].active').forEach(function (choice) {
                upstreamAddon += Number(choice.dataset.price || 0);
            });
            refreshTotal();
        }

        function recalcMonthlyAddon() {
            monthlyAddon = 0;
            document.querySelectorAll('[data-option-group]').forEach(function (group) {
                const active = group.querySelector('.config-pill.active');
                const range = group.querySelector('[data-range-input]');
                monthlyAddon += Number(active?.dataset.optionPrice || 0);
                if (range) {
                    monthlyAddon += Math.max(0, Number(range.value) - Number(range.min || 0)) * Number(range.dataset.rangeUnitPrice || 0);
                }
            });
            const osPrice = document.querySelector('[data-os-price]');
            monthlyAddon += Number(osPrice?.value || 0);
            refreshTotal();
        }

        function selectOsOption(value, label, price) {
            const input = document.querySelector('[data-option-input="ostype"]');
            const priceInput = document.querySelector('[data-os-price]');
            const summaryLabel = document.querySelector('[data-summary-label="ostype"]');
            const summaryPrice = document.querySelector('[data-summary-price="ostype"]');
            if (input) {
                input.value = value;
            }
            if (priceInput) {
                priceInput.value = price;
            }
            if (summaryLabel) {
                summaryLabel.textContent = label;
            }
            if (summaryPrice) {
                summaryPrice.textContent = Number(price) > 0 ? '+¥' + Number(price).toFixed(2) : '已包含';
            }
            recalcMonthlyAddon();
        }

        document.querySelectorAll('.config-pill[data-option-key]').forEach(function (pill) {
            pill.addEventListener('click', function () {
                const key = pill.dataset.optionKey;
                document.querySelectorAll('.config-pill[data-option-key="' + key + '"]').forEach(function (item) {
                    item.classList.remove('active');
                });
                pill.classList.add('active');
                const input = document.querySelector('[data-option-input="' + key + '"]');
                if (input) {
                    input.value = pill.dataset.optionValue;
                }
                const summaryLabel = document.querySelector('[data-summary-label="' + key + '"]');
                const summaryPrice = document.querySelector('[data-summary-price="' + key + '"]');
                if (summaryLabel) {
                    summaryLabel.textContent = pill.dataset.optionLabel;
                }
                if (summaryPrice) {
                    const optionPrice = Number(pill.dataset.optionPrice || 0);
                    summaryPrice.textContent = optionPrice > 0 ? '+¥' + optionPrice.toFixed(2) : '已包含';
                }
                monthlyAddon = 0;
                document.querySelectorAll('[data-option-group]').forEach(function (group) {
                    const active = group.querySelector('.config-pill.active');
                    const range = group.querySelector('[data-range-input]');
                    monthlyAddon += Number(active?.dataset.optionPrice || 0);
                    if (range) {
                        monthlyAddon += Math.max(0, Number(range.value) - Number(range.min || 0)) * Number(range.dataset.rangeUnitPrice || 0);
                    }
                });
                recalcMonthlyAddon();
            });
        });

        document.querySelectorAll('[data-range-input]').forEach(function (range) {
            range.addEventListener('input', function () {
                const key = range.dataset.rangeInput;
                const value = Number(range.value);
                const unit = range.dataset.rangeUnit || '';
                const numberInput = document.querySelector('[data-range-number="' + key + '"]');
                if (numberInput) {
                    numberInput.value = value;
                }
                const label = key === 'data_disk_gb' && value === 0 ? '无数据盘' : value + ' ' + unit;
                const hidden = document.querySelector('[data-option-input="' + key + '"]');
                if (hidden) {
                    hidden.value = value;
                }
                const summaryLabel = document.querySelector('[data-summary-label="' + key + '"]');
                const summaryPrice = document.querySelector('[data-summary-price="' + key + '"]');
                const rangeDisplay = document.querySelector('[data-range-display="' + key + '"]');
                const rangePrice = document.querySelector('[data-range-price="' + key + '"]');
                const price = Math.max(0, value - Number(range.min || 0)) * Number(range.dataset.rangeUnitPrice || 0);
                if (summaryLabel) {
                    summaryLabel.textContent = label;
                }
                if (rangeDisplay) {
                    rangeDisplay.textContent = label;
                }
                if (summaryPrice) {
                    summaryPrice.textContent = price > 0 ? '+¥' + price.toFixed(2) : '已包含';
                }
                if (rangePrice) {
                    rangePrice.textContent = price > 0 ? '+¥' + price.toFixed(2) : '已包含';
                }
                monthlyAddon = 0;
                document.querySelectorAll('[data-option-group]').forEach(function (group) {
                    const active = group.querySelector('.config-pill.active');
                    const rangeInput = group.querySelector('[data-range-input]');
                    monthlyAddon += Number(active?.dataset.optionPrice || 0);
                    if (rangeInput) {
                        monthlyAddon += Math.max(0, Number(rangeInput.value) - Number(rangeInput.min || 0)) * Number(rangeInput.dataset.rangeUnitPrice || 0);
                    }
                });
                recalcMonthlyAddon();
            });
        });

        document.querySelectorAll('[data-range-number]').forEach(function (numberInput) {
            numberInput.addEventListener('input', function () {
                const key = numberInput.dataset.rangeNumber;
                const range = document.querySelector('[data-range-input="' + key + '"]');
                if (!range || numberInput.value === '') {
                    return;
                }
                const value = Math.max(Number(range.min), Math.min(Number(range.max), Number(numberInput.value)));
                range.value = value;
                range.dispatchEvent(new Event('input'));
            });
        });

        const osVersionSelect = document.querySelector('[data-os-version]');
        document.querySelectorAll('[data-os-family]').forEach(function (card) {
            card.addEventListener('click', function () {
                document.querySelectorAll('[data-os-family]').forEach(function (item) {
                    item.classList.toggle('active', item === card);
                });
                if (!osVersionSelect) {
                    return;
                }
                const family = osFamilies[card.dataset.osFamily];
                if (!family) {
                    return;
                }
                osVersionSelect.innerHTML = '';
                family.options.forEach(function (option) {
                    const item = document.createElement('option');
                    item.value = option.value;
                    item.dataset.price = option.price;
                    item.textContent = option.label + (Number(option.price) > 0 ? ' (+¥' + Number(option.price).toFixed(2) + '/月)' : '');
                    osVersionSelect.appendChild(item);
                });
                const first = family.options[0];
                if (first) {
                    selectOsOption(first.value, first.label, first.price);
                }
            });
        });
        if (osVersionSelect) {
            osVersionSelect.addEventListener('change', function () {
                const option = osVersionSelect.options[osVersionSelect.selectedIndex];
                if (option) {
                    selectOsOption(option.value, option.textContent.replace(/\s*\(\+¥.*$/, ''), option.dataset.price || 0);
                }
            });
        }

        if (quantityInput) {
            quantityInput.addEventListener('input', refreshTotal);
            document.querySelector('[data-quantity-minus]')?.addEventListener('click', function () {
                quantityInput.value = Math.max(1, quantityValue() - 1);
                refreshTotal();
            });
            document.querySelector('[data-quantity-plus]')?.addEventListener('click', function () {
                quantityInput.value = Math.min(10, quantityValue() + 1);
                refreshTotal();
            });
        }

        form.querySelectorAll('input[name="billing_cycle"]').forEach(function (input) {
            input.addEventListener('change', refreshTotal);
        });

        document.querySelectorAll('[data-upstream-choice]').forEach(function (choice) {
            choice.addEventListener('click', function () {
                const group = choice.closest('[data-upstream-config-group]');
                const groupId = choice.dataset.groupId;
                if (group) {
                    group.querySelectorAll('[data-upstream-choice]').forEach(function (item) {
                        item.classList.remove('active');
                    });
                }
                choice.classList.add('active');
                const input = document.querySelector('[data-upstream-input="' + groupId + '"]');
                if (input) {
                    input.value = choice.dataset.value || '';
                }
                recalcUpstreamAddon();
            });
        });

        document.querySelectorAll('[data-upstream-os-family]').forEach(function (card) {
            const trigger = card.querySelector('[data-upstream-os-trigger]');
            if (!trigger) {
                return;
            }
            trigger.addEventListener('click', function () {
                const group = card.closest('[data-upstream-config-group]');
                if (group) {
                    group.querySelectorAll('[data-upstream-os-family]').forEach(function (item) {
                        item.classList.toggle('active', item === card);
                    });
                }
                const version = card.querySelector('[data-upstream-os-version]');
                const input = group ? group.querySelector('[data-upstream-input]') : null;
                if (version && input) {
                    input.value = version.value;
                }
            });
        });

        document.querySelectorAll('[data-upstream-os-version]').forEach(function (select) {
            select.addEventListener('change', function () {
                const card = select.closest('[data-upstream-os-family]');
                const group = select.closest('[data-upstream-config-group]');
                if (group) {
                    group.querySelectorAll('[data-upstream-os-family]').forEach(function (item) {
                        item.classList.toggle('active', item === card);
                    });
                }
                const input = group ? group.querySelector('[data-upstream-input]') : null;
                if (input) {
                    input.value = select.value;
                }
            });
        });

        recalcUpstreamAddon();
        refreshTotal();
    </script>
@endpush
