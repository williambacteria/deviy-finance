@extends('layouts.site')

@section('title', '产品选购')

@section('content')
    <section class="dv-catalog-top">
        <div class="dv-catalog-container">
            <div class="dv-catalog-breadcrumb"><a href="{{ route('home') }}">首页</a> / 产品选购</div>
            <div class="dv-catalog-title-row">
                <div>
                    <h1>产品选购</h1>
                    <p>按业务场景选择云资源。配置、带宽与周期价格透明，支持在线开通。</p>
                </div>
                <a class="dv-catalog-terms-link" href="#service-terms" data-open-terms>服务条款 →</a>
            </div>

            <div class="dv-service-notice" id="service-notice">
                <div class="dv-service-notice-title"><span>!</span> 重要服务使用须知</div>
                <div class="dv-service-notice-body">
                    <p><b>禁止用途：</b>本服务严禁用于任何非法活动，包括但不限于：垃圾邮件发送、未经授权的扫描、分布式拒绝服务攻击、挖矿等。违规使用将导致账户立即终止且不予退款。</p>
                    <p><b>退款政策：</b>具体退款政策根据不同产品有不同退款要求和计算，建议打开商品标题中链接或提交工单咨询。</p>
                </div>
                <button class="dv-service-notice-button" type="button" data-open-terms>查看完整用户条款 →</button>
            </div>
        </div>
    </section>

    <section class="section" style="padding-top:24px">
        <div class="dv-catalog-container">
            <div class="dv-catalog">
                <aside class="dv-catalog-side">
                    <form class="dv-catalog-search" method="GET" action="{{ route('products.index') }}">
                        @if ($groupSlug !== '')
                            <input type="hidden" name="group" value="{{ $groupSlug }}">
                        @endif
                        <input class="input" type="search" name="q" value="{{ $search }}" placeholder="搜索产品名称或配置">
                        <button class="btn btn-primary btn-sm" type="submit">搜索</button>
                    </form>

                    <div class="dv-catalog-side-head">产品分类</div>
                    <nav class="dv-catalog-nav">
                        @foreach ($roots as $root)
                            @php($rootChildren = $childGroups->where('parent_id', $root->id)->values())
                            @php($rootDirectCount = (int) $root->products_count)
                            @if ($rootChildren->isNotEmpty() || $rootDirectCount > 0)
                                @if ($rootChildren->isEmpty())
                                    <div class="dv-catalog-group is-direct">
                                        <a class="dv-catalog-group-head dv-catalog-direct-root {{ $selectedGroup?->id === $root->id ? 'is-active' : '' }}" href="{{ route('products.index', array_filter(['group' => $root->slug, 'q' => $search])) }}">
                                            <span class="dv-catalog-group-label">
                                                <span>{{ $root->name }}</span>
                                                <b>{{ $rootDirectCount }}</b>
                                            </span>
                                            <span class="dv-catalog-direct-arrow">→</span>
                                        </a>
                                    </div>
                                @else
                                    <div class="dv-catalog-group collapsed {{ $selectedGroup?->parent_id === $root->id ? 'is-open' : '' }}" data-group-id="{{ $root->id }}">
                                        <div class="dv-catalog-group-head">
                                            <div class="dv-catalog-group-label">
                                                <span>{{ $root->name }}</span>
                                                <b>{{ $rootChildren->sum('products_count') + $rootDirectCount }}</b>
                                            </div>
                                            <button class="dv-catalog-group-toggle" type="button" data-group-toggle aria-expanded="false" aria-label="展开{{ $root->name }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                                            </button>
                                        </div>
                                        <div class="dv-catalog-group-panel" data-group-panel>
                                            @foreach ($rootChildren as $child)
                                                <a class="dv-catalog-link dv-catalog-child {{ $selectedGroup?->id === $child->id ? 'active' : '' }}" href="{{ route('products.index', array_filter(['group' => $child->slug, 'q' => $search])) }}">
                                                    <span>{{ $child->name }}</span>
                                                    <b>{{ $child->products_count }}</b>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        @endforeach
                    </nav>

                    <div class="dv-catalog-help">
                        <b>需要定制配置？</b>
                        <p>CPU、内存、数据盘与带宽均可在商品页自由组合。</p>
                        <a href="{{ route('console.tickets.create') }}">联系技术支持 →</a>
                    </div>
                </aside>

                <main class="dv-catalog-main" id="dv-catalog-main">
                    <div class="dv-catalog-head">
                        <div>
                            <div class="dv-kicker">当前分类</div>
                            <h2>{{ $selectedGroup?->name ?? '产品分类' }}</h2>
                            <p>{{ $selectedGroup?->description ?: '按下单周期计费，支持月付、季付、半年付与年付。' }}</p>
                        </div>
                        <span class="dv-catalog-count">{{ collect($sections)->sum(fn ($section) => $section['products']->count()) }} 款产品</span>
                    </div>

                    @forelse ($sections as $section)
                        <section class="dv-catalog-section">
                            @if ($section['group']->name !== ($selectedGroup?->name ?? ''))
                                <div class="dv-catalog-section-head">
                                    <h3>{{ $section['group']->name }}</h3>
                                    <span>{{ $section['products']->count() }} 款</span>
                                </div>
                            @endif
                            <div class="dv-catalog-grid">
                                @foreach ($section['products'] as $product)
                                    @php($specs = $product->metadata['specs'] ?? [])
                                    @php($options = \App\Support\ProductOptions::options($product))
                                    @php($basePrice = $product->prices->firstWhere('billing_cycle', 'monthly') ?? $product->prices->first())
                                    @php($presentation = is_array($product->metadata['presentation'] ?? null) ? $product->metadata['presentation'] : [])
                                    @php($introHtml = trim((string) ($presentation['html'] ?? '')))
                                    @php($introCss = (string) ($presentation['css'] ?? ''))
                                    @php($details = is_array($product->metadata['details'] ?? null) ? $product->metadata['details'] : [])
                                    @php($isMofangProduct = $product->upstream_source === 'mofang' || data_get($product->metadata, 'upstream') === 'mofang')
                                    @php($upstreamSpecs = is_array(data_get($product->metadata, 'upstream_specs')) ? data_get($product->metadata, 'upstream_specs') : [])
                                    @php($upstreamStock = data_get($product->metadata, 'upstream_stock'))
                                    @php($displayStock = $isMofangProduct && $upstreamStock !== null ? (int) $upstreamStock : (int) $product->stock)
                                    <article class="dv-catalog-card{{ $introHtml !== '' ? ' dv-catalog-card-custom' : '' }}">
                                        <div class="dv-catalog-card-head">
                                            <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
                                            <span class="dv-catalog-availability{{ $displayStock > 0 ? '' : ' is-out' }}" title="当前库存 {{ $displayStock }} 台">{{ $product->type === 'colocation' ? '人工确认机位' : ($displayStock > 0 ? '有货 · '.$displayStock.' 台' : '无货') }}</span>
                                        </div>
                                        @if (\App\Support\ServicePresentation::kind($product) === 'cdn')
                                            <dl class="dv-catalog-specs" data-cdn-package>
                                                @forelse (\App\Support\ServicePresentation::cdnFields($product) as $label => $value)
                                                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                                @empty
                                                    <div><dt>服务</dt><dd>CDN 加速</dd></div>
                                                @endforelse
                                            </dl>
                                        @elseif ($isMofangProduct && $upstreamSpecs !== [])
                                            <dl class="dv-catalog-specs">
                                                @foreach ($upstreamSpecs as $upstreamSpec)
                                                    <div><dt>{{ $upstreamSpec['label'] ?? '' }}</dt><dd>{{ $upstreamSpec['value'] ?? '' }}</dd></div>
                                                @endforeach
                                            </dl>
                                        @elseif ($details !== [])
                                            @php($cardIntro = trim((string) ($product->metadata['card_intro'] ?? '')))
                                            @if ($cardIntro !== '')
                                                <p>{{ $cardIntro }}</p>
                                            @endif
                                            <dl class="dv-catalog-specs">
                                                @foreach ($details as $detail)
                                                    <div><dt>{{ $detail['label'] ?? '' }}</dt><dd>{{ $detail['value'] ?? '' }}</dd></div>
                                                @endforeach
                                            </dl>
                                        @elseif ($introHtml !== '')
                                            @if ($introCss !== '')
                                                <style>{!! $introCss !!}</style>
                                            @endif
                                            <div class="dv-catalog-intro">{!! $introHtml !!}</div>
                                        @elseif ($isMofangProduct)
                                            <p>{{ \Illuminate\Support\Str::limit(trim((string) $product->short_description), 180) }}</p>
                                        @else
                                            <p>{{ $product->short_description ?: ($product->region.' · 稳定云资源') }}</p>
                                            <dl class="dv-catalog-specs">
                                                <div><dt>CPU</dt><dd>{{ $options['cpu'][0]['label'] ?? (($specs['cpu'] ?? 2).' 核') }}</dd></div>
                                                <div><dt>内存</dt><dd>{{ $options['memory_gb'][0]['label'] ?? (($specs['memory_gb'] ?? 4).' GB') }}</dd></div>
                                                <div><dt>数据盘</dt><dd>{{ $options['data_disk_gb'][0]['label'] ?? '可选' }}</dd></div>
                                                <div><dt>上行</dt><dd>{{ $options['upload_mbps'][0]['label'] ?? (($specs['upload_mbps'] ?? $specs['bandwidth_mbps'] ?? 10).' Mbps') }}</dd></div>
                                                <div><dt>下行</dt><dd>{{ $options['download_mbps'][0]['label'] ?? (($specs['download_mbps'] ?? $specs['bandwidth_mbps'] ?? 10).' Mbps') }}</dd></div>
                                                <div><dt>系统</dt><dd>{{ $options['ostype'][0]['label'] ?? 'Linux / Windows' }}</dd></div>
                                            </dl>
                                        @endif
                                        <div class="dv-catalog-card-foot">
                                            <div class="dv-catalog-price">
                                                <b>¥{{ number_format((float) ($basePrice?->price ?? 0), 2) }}</b>
                                                <small>起/{{ $basePrice ? \App\Support\Billing::label($basePrice->billing_cycle) : '月' }}</small>
                                            </div>
                                            <a class="btn btn-primary btn-sm" href="{{ route('products.show', $product) }}">{{ $product->type === 'colocation' ? '了解托管 →' : '立即购买 →' }}</a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="dv-catalog-empty">
                            <x-icon name="search" :size="24" />
                            <h3>没有找到匹配的产品</h3>
                            <p>试试更换关键词，或返回默认分类。</p>
                            <a class="btn btn-primary btn-sm" href="{{ route('products.index') }}">返回默认分类</a>
                        </div>
                    @endforelse
                </main>
            </div>
        </div>
    </section>

    <dialog class="dv-terms-dialog" id="service-terms">
        <div class="dv-terms-head">
            <div><b>服务使用条款</b><small>Deviy Cloud 服务协议摘要</small></div>
            <button type="button" data-close-terms aria-label="关闭">×</button>
        </div>
        <div class="dv-terms-body"><h3>服务使用与网络安全</h3><p>请按商品约定合法使用资源，禁止未经授权的攻击、扫描、垃圾邮件及其他危害网络安全的行为。</p><h3>计费、到期与退款</h3><p>具体配置、费用、续费、退款及数据保留按商品、订单与适用协议处理。请在到期或执行不可逆操作前做好独立备份。</p><h3>阅读完整协议</h3><p>完整条款说明了服务范围、账户责任、数据安全、维护、终止与争议处理。隐私政策说明了个人信息处理及权利申请方式。</p><p><a href="{{ route('terms') }}" target="_blank" rel="noopener">阅读完整服务条款 ↗</a>　<a href="{{ route('privacy') }}" target="_blank" rel="noopener">阅读隐私政策 ↗</a></p></div>
        <div class="dv-terms-foot">下单前请仔细阅读商品说明与适用服务条款。</div>
    </dialog>
@endsection

@push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        const termsDialog = document.getElementById('service-terms');
        document.querySelectorAll('[data-open-terms]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                if (typeof termsDialog.showModal === 'function') {
                    termsDialog.showModal();
                } else {
                    termsDialog.setAttribute('open', 'open');
                }
            });
        });
        document.querySelectorAll('[data-close-terms]').forEach(function (button) {
            button.addEventListener('click', function () {
                termsDialog.close();
            });
        });
        termsDialog.addEventListener('click', function (event) {
            if (event.target === termsDialog) {
                termsDialog.close();
            }
        });

        (function () {
            const storageKey = 'dv-catalog-expanded';
            let expanded = [];

            try {
                expanded = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
            } catch (error) {
                expanded = [];
            }

            if (!Array.isArray(expanded)) {
                expanded = [];
            }

            const saveExpanded = function () {
                try {
                    sessionStorage.setItem(storageKey, JSON.stringify(expanded));
                } catch (error) {
                    // Ignore storage failures in private browsing mode.
                }
            };

            document.querySelectorAll('.dv-catalog-group[data-group-id]').forEach(function (group) {
                const groupId = String(group.dataset.groupId);
                const button = group.querySelector('[data-group-toggle]');

                if (expanded.includes(groupId)) {
                    group.classList.remove('collapsed');
                    if (button) {
                        button.setAttribute('aria-expanded', 'true');
                    }
                }

                if (button) {
                    button.addEventListener('click', function () {
                        const collapsed = group.classList.toggle('collapsed');
                        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

                        const index = expanded.indexOf(groupId);
                        if (collapsed) {
                            if (index !== -1) {
                                expanded.splice(index, 1);
                            }
                        } else if (index === -1) {
                            expanded.push(groupId);
                        }
                        saveExpanded();
                    });
                }
            });
        }());

        (function () {
            const main = document.getElementById('dv-catalog-main');
            if (!main) {
                return;
            }

            const setActiveGroup = function (href) {
                const target = new URL(href, window.location.origin);
                const targetKey = target.pathname + target.search;

                document.querySelectorAll('.dv-catalog-side a').forEach(function (link) {
                    const linkUrl = new URL(link.href, window.location.origin);
                    const key = linkUrl.pathname + linkUrl.search;
                    const isChild = link.classList.contains('dv-catalog-child');
                    link.classList.toggle(isChild ? 'active' : 'is-active', key === targetKey);
                });

                const group = target.searchParams.get('group') || '';
                const hidden = document.querySelector('.dv-catalog-search input[name="group"]');
                if (hidden) {
                    hidden.value = group;
                }
            };

            const loadGroup = async function (href, pushState) {
                main.classList.add('is-loading');
                main.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    });
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const html = await response.text();
                    const parsed = new DOMParser().parseFromString(html, 'text/html');
                    const nextMain = parsed.getElementById('dv-catalog-main');
                    if (!nextMain) {
                        throw new Error('Missing catalog content');
                    }

                    main.innerHTML = nextMain.innerHTML;
                    setActiveGroup(href);

                    if (pushState) {
                        history.pushState({}, '', href);
                    }
                } catch (error) {
                    window.location.href = href;
                } finally {
                    main.classList.remove('is-loading');
                    main.removeAttribute('aria-busy');
                }
            };

            document.querySelectorAll('.dv-catalog-side .dv-catalog-child, .dv-catalog-side .dv-catalog-direct-root').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') {
                        return;
                    }
                    event.preventDefault();
                    loadGroup(link.href, true);
                });
            });

            window.addEventListener('popstate', function () {
                loadGroup(window.location.href, false);
            });
        }());
    </script>
@endpush
