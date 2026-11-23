@extends('layouts.console')
@section('title', '域名过白')
@section('page-title', '域名过白')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/domain-allowlist.css') }}?v={{ filemtime(public_path('assets/css/domain-allowlist.css')) }}">
@endpush
@section('content')
<div class="domain-allowlist-page">
    <header class="page-head"><div><span class="domain-eyebrow">域名接入支持</span><h1>域名过白</h1><p>选择服务器所在机房，提交域名与公网 IP，查看机房返回的处理结果。</p></div><a class="btn btn-ghost" href="{{ route('console.tickets.create') }}"><x-icon name="ticket" :size="16" />遇到问题，提交工单</a></header>
    <section class="domain-guide" aria-label="提交指引"><span><x-icon name="shield" :size="24" /></span><div><h2>主域名提交一次，按机房规则生效</h2><p>请填写已经完成 ICP 备案的主域名，例如 <code>example.com</code>，不要包含 https://、路径或端口。备案状态会由接口核验，过白范围以机房实际返回为准。</p><div><span>01 选择机房</span><span>02 填写主域名与 IP</span><span>03 核对处理结果</span><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer">查询备案信息 ↗</a></div></div></section>
    @if ($errors->any())<div class="note danger domain-feedback" role="alert">{{ $errors->first() }}</div>@endif
    @if ($lastRequest)
        <section class="domain-latest {{ in_array($lastRequest->status, ['success', 'already_whitelisted'], true) ? 'is-success' : (in_array($lastRequest->status, ['failed', 'rejected'], true) ? 'is-error' : '') }}" role="status" aria-live="polite"><span><x-icon :name="in_array($lastRequest->status, ['success', 'already_whitelisted'], true) ? 'check' : 'info'" :size="23" /></span><div><h2>{{ $statuses[$lastRequest->status] ?? '结果待核对' }}</h2><p><b>{{ $lastRequest->domain }}</b> · {{ $regions[$lastRequest->region]['name'] }} · {{ $lastRequest->target_ip }}</p><p>{{ $lastRequest->message }}</p></div></section>
    @endif
    <div class="domain-region-grid">
        @foreach ($regions as $key => $region)
        <section class="panel domain-region-card"><div class="domain-region-top"><span class="domain-region-icon"><x-icon :name="$region['icon']" :size="23" /></span><div><h2>{{ $region['name'] }}机房</h2><p>{{ $region['description'] }}</p></div><span class="domain-region-pill">{{ $key === 'sy' ? '人工审核' : '接口处理' }}</span></div>
            <form method="POST" action="{{ route('console.domain-allowlist.store') }}" class="domain-region-form" data-domain-form>
                @csrf<input type="hidden" name="region" value="{{ $key }}">
                <div class="form-field"><label class="form-label" for="domain-{{ $key }}">主域名</label><input class="input" id="domain-{{ $key }}" name="domain" value="{{ old('region') === $key ? old('domain') : '' }}" placeholder="example.com" maxlength="253" autocomplete="off" spellcheck="false" autocapitalize="none" required></div>
                <div class="form-field"><label class="form-label" for="ip-{{ $key }}">服务器公网 IPv4</label><input class="input" id="ip-{{ $key }}" name="target_ip" value="{{ old('region') === $key ? old('target_ip') : '' }}" placeholder="填写该机房服务器公网 IP" maxlength="15" inputmode="decimal" autocomplete="off" required></div>
                <button class="btn btn-primary" type="submit"><span>提交过白申请</span><x-icon name="arrow-right" :size="16" /></button><p class="domain-submit-progress" role="status" aria-live="polite" data-domain-progress></p>
            </form>
            <footer><x-icon name="{{ $key === 'sy' ? 'clock' : 'shield' }}" :size="15" /><span>{{ $key === 'sy' ? '十堰为人工审核，处理进度以机房实际结果为准，请勿连续提交。' : '自动校验备案与请求参数；仅明确返回成功才标记为过白成功。' }}</span></footer>
        </section>
        @endforeach
    </div>
    <section class="panel domain-history" aria-labelledby="domain-history-title"><div class="panel-head"><div><h2 id="domain-history-title">我的过白记录</h2><p>仅显示当前账户记录；结果为最近一次提交时的机房回执。</p></div><span class="meta">{{ $history->total() }} 条</span></div>
        <form method="GET" class="domain-history-filters"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="搜索域名或公网 IP" aria-label="搜索过白记录"><select class="select" name="region" aria-label="筛选机房"><option value="">全部机房</option>@foreach ($regions as $key => $region)<option value="{{ $key }}" @selected($selectedRegion === $key)>{{ $region['name'] }}</option>@endforeach</select><button type="submit" class="btn btn-ghost">查询</button><a class="btn btn-ghost" href="{{ route('console.domain-allowlist.index') }}">重置</a></form>
        <div class="domain-record-list">@forelse ($history as $entry)
            <article class="domain-record" data-domain-record><header><div><h3>{{ $entry->domain }}</h3><p><span>{{ $regions[$entry->region]['name'] ?? $entry->region }}</span><code>{{ $entry->target_ip }}</code></p></div><span class="domain-state state-{{ $entry->status }}">{{ $statuses[$entry->status] ?? '结果待核对' }}</span></header><p class="domain-record-message">{{ $entry->message }}</p><footer><span>备案：{{ ['filed' => '已备案', 'not_filed' => '未备案', 'error' => '查询异常', 'failed' => '查询异常'][$entry->icp_status] ?? '未取得核验结果' }}</span><time>{{ $entry->submitted_at?->format('Y-m-d H:i') }}</time>@if ($entry->canResubmit())<form method="POST" action="{{ route('console.domain-allowlist.store') }}" data-domain-form data-confirm="将再次向机房提交这组域名与 IP，以核对或重试处理。确定继续？">@csrf<input type="hidden" name="region" value="{{ $entry->region }}"><input type="hidden" name="domain" value="{{ $entry->domain }}"><input type="hidden" name="target_ip" value="{{ $entry->target_ip }}"><input type="hidden" name="resubmit" value="1"><button type="submit" class="btn btn-ghost btn-sm">重新提交／核对</button><span class="domain-submit-progress" data-domain-progress role="status"></span></form>@endif</footer></article>
        @empty<div class="domain-empty"><x-icon name="globe" :size="30" /><h3>暂无过白记录</h3><p>在上方选择对应机房，提交后会在这里保存真实回执。</p></div>@endforelse</div>
        @if ($history->hasPages())<div class="panel-footer">{{ $history->links('partials.pagination') }}</div>@endif
    </section>
    <p class="domain-bottom-note"><x-icon name="info" :size="15" /><span>请仅提交你有权管理的域名与 IP。未备案、机房不匹配或人工审核中的申请，不会直接显示过白成功。接口超时请先联系支持核对。</span></p>
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/js/domain-allowlist.js') }}?v={{ filemtime(public_path('assets/js/domain-allowlist.js')) }}" defer></script>
@endpush
