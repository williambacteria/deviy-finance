@php($price = $product->prices->sortBy('price')->first())
<a class="product-card" href="{{ route('products.show', $product) }}">
    <div class="flag">{{ strtoupper($product->region) }} · {{ $product->group->name ?? 'CLOUD' }}</div>
    <h3>{{ $product->name }}</h3>
    <div class="desc">{{ $product->short_description }}</div>
    <div class="specs">
        @if (\App\Support\ServicePresentation::kind($product) === 'cdn')
            <span>CDN 加速</span><span>域名接入</span><span>专用控制台</span>
        @elseif ($product->type === 'cloud_vm')
            <span>KVM</span><span>独立 IP</span><span>SSD</span>
        @elseif ($product->type === 'nat_vm')
            <span>NAT</span><span>端口映射</span><span>共享出口</span>
        @else
            <span>{{ $product->type === 'dedicated_ip' ? '独立 IP' : '网络资源' }}</span>
            <span>即开即用</span>
        @endif
    </div>
    <div class="price">
        @if ($price)
            <b>{{ \App\Support\Money::format($price->price) }}</b><small>/ {{ \App\Support\Billing::label($price->billing_cycle) }}起</small>
        @else
            <b>询价</b>
        @endif
    </div>
    <span class="link">查看配置 <x-icon name="arrow-right" :size="13" /></span>
</a>
