@extends('layouts.admin')

@section('title', '已售实例 · '.$product->name)
@section('page-title', '已售实例')

@section('content')
    <div class="page-head">
        <div>
            <h1>已售实例</h1>
            <p>{{ $product->name }} · {{ $product->code }} · 共 {{ $soldCount }} 个实例</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">返回商品设置</a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>实例与拥有者</h2>
            <span class="meta">库存 {{ $product->stock }} · 已售 {{ $soldCount }}</span>
        </div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>实例</th><th>拥有者</th><th>状态</th><th>开通时间</th><th>到期时间</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td>
                                <a class="strong" href="{{ route('admin.services.show', $service) }}">{{ $service->service_no }}</a>
                                <div class="sub">{{ $service->name ?: $service->external_ref }}</div>
                            </td>
                            <td>
                                @if ($service->user)
                                    <a class="strong" href="{{ route('admin.customers.show', $service->user) }}">{{ $service->user->name }}</a>
                                    <div class="sub">{{ $service->user->email }}</div>
                                @else
                                    <span class="muted">客户已删除</span>
                                @endif
                            </td>
                            <td><x-status :status="$service->status" /></td>
                            <td class="small">{{ $service->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="small">{{ $service->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('admin.services.show', $service) }}">查看实例</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">该商品还没有已售实例。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($services->hasPages())
                <div class="panel-footer">{{ $services->links('partials.pagination') }}</div>
            @endif
        </div>
    </div>
@endsection
