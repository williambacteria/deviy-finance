@extends('layouts.console')

@section('title', '我的资源')
@section('page-title', '我的资源')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/resource-groups.css') }}?v={{ filemtime(public_path('assets/css/resource-groups.css')) }}">
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>我的资源</h1>
            <p>查看和管理你的云资源、连接信息与到期时间。</p>
        </div>
        <div class="page-actions">
            <form method="GET" class="filter-bar">
                @if ($selectedGroupKey !== '')<input type="hidden" name="group" value="{{ $selectedGroupKey }}">@endif
                <input class="input" type="search" name="q" value="{{ $resourceSearch }}" placeholder="名称 / 编号 / IP" aria-label="搜索资源"><select class="select" name="status" aria-label="资源状态">
                    <option value="">全部状态</option>
                    @foreach (['active' => '运行中', 'pending' => '待开通', 'stopped' => '已关机', 'suspended' => '已暂停', 'provisioning' => '开通中', 'terminated' => '已终止'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select><button class="btn btn-ghost" type="submit">查询</button>
            </form>
            <a class="btn btn-primary" href="{{ route('products.index') }}"><x-icon name="plus" :size="15" /> 选购资源</a>
        </div>
    </div>

    @if ($resourceTotal > 0)
        <nav class="owned-resource-groups" aria-labelledby="owned-resource-groups-title">
            <div class="owned-resource-groups-head">
                <h2 id="owned-resource-groups-title">资源分组</h2>
                <span>{{ count($groupSummaries) }} 个分组</span>
            </div>
            <ul class="owned-resource-group-grid">
                @foreach ([['key' => '', 'name' => '全部资源', 'icon' => 'dashboard', 'tone' => 'blue', 'total' => $resourceTotal, 'running' => $runningTotal], ...array_values($groupSummaries)] as $groupSummary)
                    @php($isCurrent = $selectedGroupKey === $groupSummary['key'])
                    <li>
                        <a @class(['owned-resource-group', 'is-current' => $isCurrent]) data-tone="{{ $groupSummary['tone'] }}"
                            href="{{ route('console.services.index', array_filter(['group' => $groupSummary['key'], 'status' => $status, 'q' => $resourceSearch], fn ($value) => $value !== '')) }}"
                            @if ($isCurrent) aria-current="page" @endif>
                            <span class="owned-resource-group-icon" aria-hidden="true"><x-icon :name="$groupSummary['icon']" :size="18" /></span>
                            <span class="owned-resource-group-label">
                                <span class="owned-resource-group-name">{{ $groupSummary['name'] }}</span>
                                <span class="owned-resource-group-counts"><span>{{ $groupSummary['total'] }} 个实例</span>@if ($groupSummary['running'] > 0)<span class="owned-resource-group-running">{{ $groupSummary['running'] }} 个运行中</span>@endif</span>
                            </span>
                            @if ($isCurrent)<span class="owned-resource-group-check" aria-hidden="true"><x-icon name="check" :size="15" /></span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="panel">
        <div class="panel-head"><h2>{{ $selectedGroup['name'] ?? '资源列表' }}</h2><span class="meta">{{ $services->count() }} 个实例</span></div>
        <div class="panel-body tight">
            @if ($services->count())
                <div class="cloud-resource-list cloud-resource-list--grid">@foreach ($services as $service)@include('console.services.card')@endforeach</div>
            @else
                <div class="empty-state">
                    <div class="empty-icon"><x-icon name="server" :size="23" /></div>
                    <h3>{{ $resourceTotal ? '没有匹配的实例' : '暂无资源' }}</h3>
                    @if ($resourceTotal)
                        <a class="btn btn-ghost btn-sm" href="{{ route('console.services.index', $selectedGroupKey === '' ? [] : ['group' => $selectedGroupKey]) }}">清除筛选</a>
                    @else
                        <a class="btn btn-primary btn-sm" href="{{ route('products.index') }}">选购资源</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
