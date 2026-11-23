<div class="dv-product-grid sky-featured-grid" data-homepage-featured>
                @forelse ($featuredProducts as $product)
                    @php($monthly = $product->prices->firstWhere('billing_cycle', 'monthly'))
                    @php($rawDescription = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', (string) $product->short_description))
                    @php($rawDescription = preg_replace('#<\s*br\s*/?\s*>|</\s*(?:p|div|li|section|tr)\s*>#i', ' · ', $rawDescription))
                    @php($summary = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rawDescription), ENT_QUOTES | ENT_HTML5, 'UTF-8')), " \t\n\r\0\x0B·|,，;；"))
                    @php($summary = (string) preg_replace('/(?<=[A-Za-z])(?=\d)|(?<=[A-Za-z0-9])(?=(?:内存|系统盘|数据盘|上行|下行|带宽|流量|线路|测试IP|IP地址))/u', ' ', $summary))
                    @php($featured = $featuredMeta[$product->id] ?? null)
                    @php($featuredIcons = ['fire' => '<path d="M12 22c4 0 7-3.1 7-7 0-3.2-2-5.7-4.2-8.1-.8-.9-1.5-1.9-2-3.1-.2-.5-.9-.6-1.2-.1-1.1 2-2.7 3.5-4.1 5.2C5.6 11.2 5 13 5 15c0 3.9 3 7 7 7Z"/><path d="M9.5 15.5c0-1.7 1.2-2.9 2.5-4.3 1.3 1.4 2.5 2.6 2.5 4.3a2.5 2.5 0 0 1-5 0Z"/>', 'spark' => '<path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="m19 16 .8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16Z"/>', 'gift' => '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M7.5 8C6 8 5 6.9 5 5.5S6 3 7.5 3C10 3 12 8 12 8s2-5 4.5-5C18 3 19 4.1 19 5.5S18 8 16.5 8"/>', 'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>', 'crown' => '<path d="m3 8 4 4 5-7 5 7 4-4-2 11H5L3 8Z"/><path d="M5 19h14"/>', 'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1.5"/>'])
                    <a class="dv-product-card sky-product-card" data-featured-product="{{ $product->id }}" href="{{ route('products.show', $product) }}">
                        <div class="sky-product-top">
                        <div class="dv-product-type">{{ ['cloud_vm' => '云服务器', 'nat_vm' => 'NAT 云主机', 'dedicated_ip' => '独立 IP', 'bandwidth' => '带宽', 'cdn' => 'CDN 加速', 'bare_metal' => '裸金属服务器'][$product->type] ?? '云资源' }}</div>
                        @if ($featured && (($featured['label'] ?? '') !== '' || ($featured['icon'] ?? 'fire') !== 'fire'))
                            @php($featuredLabel = trim((string) ($featured['label'] ?? '')))
                            @php($featuredIcon = $featured['icon'] ?? 'fire')
                            <span class="dv-featured-badge dv-featured-{{ $featuredIcon }} {{ $featuredLabel === '' ? 'is-icon-only' : '' }}" title="{{ $featuredLabel ?: ['fire' => '热门', 'spark' => '新上', 'gift' => '活动', 'bolt' => '极速', 'crown' => '尊享', 'tag' => '标签'][$featuredIcon] ?? '标签' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $featuredIcons[$featuredIcon] ?? $featuredIcons['fire'] !!}</svg>
                                @if ($featuredLabel !== ''){{ $featuredLabel }}@endif
                            </span>
                        @endif
                        </div>
                        <h3>{{ $product->name }}</h3>
                        <p>{{ $summary ?: ($product->region.' · 稳定云资源') }}</p>
                        <div class="sky-product-region"><x-icon name="globe" :size="13" /><span>{{ $product->region ?: '云端资源' }}</span></div>
                        <div class="dv-product-foot">
                            <span><b>¥{{ number_format((float) ($monthly?->price ?? 0), 2) }}</b> / 月起</span>
                            <span class="dv-product-link">立即购买 →</span>
                        </div>
                    </a>
                @empty
                    <div class="dv-product-empty">产品正在上架中，请稍后再来。</div>
                @endforelse
</div>
