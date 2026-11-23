@php
    $resource = \App\Support\ServicePresentation::service($service);
    $icon = match ($resource['kind']) { 'cdn' => 'globe', 'bare_metal' => 'hardware-chip', 'colocation' => 'building', 'game_server' => 'terminal', default => 'cloud' };
    $pendingDeletion = (bool) data_get($service->specs, 'admin_deletion');
@endphp
<article class="cloud-resource-card" data-cloud-resource>
    <header><span class="cloud-resource-family"><x-icon :name="$icon" :size="18" />{{ $resource['family'] }}</span>@if ($pendingDeletion)<span class="badge warning">终止处理中</span>@elseif ($resource['kind'] === 'colocation')<span class="chip">{{ $service->expires_at?->isPast() ? '已到期' : '托管中' }}</span>@else<x-status :status="$service->status" />@endif</header>
    <h3><a href="{{ route('console.services.show', $service) }}">{{ $resource['title'] }}</a></h3>
    <p class="cloud-resource-reference">{{ $service->service_no }} · {{ $service->product?->name }}</p>
    <p class="cloud-resource-specs">{{ $resource['summary'] }}</p>
    <dl class="cloud-resource-facts"><div><dt>{{ $resource['network_label'] }}</dt><dd><span>{{ $resource['network'] }}</span>@if (! $resource['is_cdn'] && ! in_array($resource['network'], ['待同步', '未登记', '公网映射待就绪'], true))<button type="button" data-copy="{{ $resource['network'] }}" aria-label="复制连接地址">复制</button>@endif</dd></div><div><dt>{{ $resource['capacity_label'] }}</dt><dd>{{ $resource['capacity'] }}</dd></div><div><dt>到期时间</dt><dd>{{ $service->expires_at?->format('Y-m-d') ?? '未设置' }}@if ($service->expires_at)<small>{{ $service->expires_at->format('H:i') }}</small>@endif</dd></div><div><dt>续费参考 · {{ \App\Support\Billing::label($service->billing_cycle ?: 'monthly') }}</dt><dd>{{ isset($serviceRenewalQuotes[$service->id]) ? \App\Support\Money::format($serviceRenewalQuotes[$service->id]) : ($resource['kind'] === 'colocation' ? (\App\Support\Colocation::monthlyRenewal($service) ?? '联系机房确认') : '进入详情查看') }}</dd></div></dl>
    <footer><span><x-icon name="globe" :size="14" />{{ $resource['location'] }}</span><div><a class="btn btn-soft btn-sm" href="{{ route('console.services.show', $service) }}">管理资源 →</a>@if ($resource['reboot'] && ! $pendingDeletion)<form method="POST" action="{{ route('console.services.action', $service) }}" data-confirm="确定重启这项资源？运行中的业务会短暂中断。">@csrf<input type="hidden" name="action" value="reboot"><button class="btn btn-ghost btn-sm" type="submit">重启</button></form>@endif</div></footer>
</article>
