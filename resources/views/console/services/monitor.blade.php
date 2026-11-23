@extends('layouts.console')

@section('title', '资源监控 · '.$service->name)
@section('page-title', '资源监控')

@section('content')
    <div class="page-head monitor-page-head">
        <div>
            <div class="flex items-center gap-3" style="flex-wrap:wrap">
                <h1>资源监控</h1>
                <x-status :status="$service->status" />
            </div>
            <p>{{ $service->product?->name }} · {{ $service->service_no }} · {{ $service->os }}</p>
            <p class="monitor-tip">拖动图表左右平移 · 滚轮缩放时间轴 · 悬停查看具体数值</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-ghost" href="{{ route('console.services.show', $service) }}">返回实例详情</a>
            <a class="btn btn-primary" href="{{ route('console.services.vnc', $service) }}" rel="noopener" data-vnc-popup="{{ $service->id }}">VNC 控制台</a>
        </div>
    </div>

    @if ($upstreamCharts !== [])
        <div class="panel monitor-page-panel">
            <div class="panel-body">
                <div class="upstream-chart-grid monitor-page-charts" data-upstream-charts="{{ json_encode($upstreamCharts, JSON_UNESCAPED_UNICODE) }}">
                    @foreach (['cpu' => 'CPU使用量', 'memory' => '内存用量', 'disk' => '磁盘IO', 'bandwidth' => '网卡'] as $chartKey => $chartTitle)
                        @php $chart = $upstreamCharts[$chartKey] ?? null; @endphp
                        @if (is_array($chart))
                            <div class="upstream-chart-card" data-upstream-chart-card="{{ $chartKey }}">
                                <div class="upstream-chart-head">
                                    <h3>{{ $chart['title'] ?? $chartTitle }}</h3>
                                    <div class="upstream-ranges">
                                        <button type="button" class="active" data-upstream-range="30m">30 分钟</button>
                                        <button type="button" data-upstream-range="24h">24 小时</button>
                                        <button type="button" data-upstream-range="7d">7 天</button>
                                        <button type="button" data-upstream-range="30d">30 天</button>
                                        <button type="button" data-upstream-range="custom">自定义</button>
                                    </div>
                                </div>
                                <div class="upstream-custom-range" hidden>
                                    <input type="datetime-local" data-upstream-start aria-label="开始时间">
                                    <span>至</span>
                                    <input type="datetime-local" data-upstream-end aria-label="结束时间">
                                    <button class="btn btn-primary btn-sm" type="button" data-upstream-apply>应用</button>
                                </div>
                                <div class="upstream-chart-wrap">
                                    <svg class="upstream-chart" data-upstream-chart></svg>
                                    <div class="upstream-chart-tooltip" data-upstream-tooltip hidden></div>
                                </div>
                                <div class="upstream-chart-legend">
                                    @foreach (($chart['series'] ?? []) as $series)
                                        <span><i class="series-dot series-{{ $chartKey }}-{{ $loop->index }}"></i>{{ $series['name'] ?? '数据' }}（{{ $chart['unit'] ?? '' }}）</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="panel"><div class="panel-body"><div class="note">暂未获取到监控数据，请稍后刷新。</div></div></div>
    @endif
@endsection
