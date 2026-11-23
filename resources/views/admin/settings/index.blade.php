@extends('layouts.admin')
@section('title', '系统设置 · '.$definition['title'])
@section('page-title', '系统设置')
@section('content')
@include('admin.organization-style')
<div class="admin-settings-shell">
    <header class="admin-section-heading"><div><span class="admin-eyebrow">平台管理</span><h1>系统设置</h1><p>站点、计费、支付与基础服务，集中配置并即时接入业务。</p></div><a class="btn btn-ghost" href="{{ route('admin.workspace', ['area' => 'messages']) }}"><x-icon name="bell" :size="16" /> 消息与告警</a></header>
    <div class="admin-settings-layout">
        <nav class="admin-settings-nav" aria-label="系统设置分类">
            @foreach (collect($sections)->groupBy('category', preserveKeys: true) as $category => $items)
                <div class="admin-settings-category">{{ $category }}</div>
                @foreach ($items as $key => $item)
                    <a href="{{ route('admin.settings.index', ['section' => $key]) }}" @class(['admin-settings-link', 'is-active' => $section === $key]) @if ($section === $key) aria-current="page" @endif><x-icon :name="$item['icon']" :size="17" /><span>{{ $item['title'] }}</span></a>
                @endforeach
            @endforeach
        </nav>
        <div class="admin-settings-content" data-settings-section="{{ $section }}">
            @if ($fields !== [])
                <section class="panel admin-settings-form"><div class="panel-head"><h2><x-icon :name="$definition['icon']" :size="19" /> {{ $definition['title'] }}</h2><span class="meta">已接入当前站点</span></div><div class="panel-body">
                    @if ($group === 'billing')<div class="note info" style="margin-bottom:20px">结算币种固定为人民币。更改编号仅影响新单；已迁入客户的专属续费价格继续保留，商品定价请在商品中心维护。</div>@endif
                    @if ($group === 'notification')<div class="note info" style="margin-bottom:20px">邮件服务器在“邮件通道”设置。下方统一管理业务通知开关、发送限额、日志和消息模板。</div>@endif
                    <form method="POST" action="{{ route('admin.settings.update') }}" data-settings-form>@csrf @method('PUT')<input type="hidden" name="group" value="{{ $group }}"><div class="admin-setting-fields">
                        @foreach ($fields as $key => $field)
                            <div class="form-field"><label class="form-label" for="setting-{{ $key }}">{{ $field['label'] }}</label>
                                @if (($field['type'] ?? '') === 'boolean')
                                    <select class="select" id="setting-{{ $key }}" name="settings[{{ $key }}]"><option value="true" @selected(old('settings.'.$key, $values[$key] ?? $field['default']) === 'true')>开启</option><option value="false" @selected(old('settings.'.$key, $values[$key] ?? $field['default']) === 'false')>关闭</option></select>
                                @else
                                    <input class="input" id="setting-{{ $key }}" type="{{ ($field['type'] ?? '') === 'number' ? 'number' : (str_contains($key, 'email') ? 'email' : 'text') }}" name="settings[{{ $key }}]" value="{{ old('settings.'.$key, $values[$key] ?? $field['default']) }}" @if (($field['type'] ?? '') === 'number') step="1" min="{{ $group === 'billing' && $key === 'grace_days' ? 0 : 1 }}" @endif aria-describedby="help-{{ $key }}">
                                @endif
                                <p class="form-help" id="help-{{ $key }}">{{ $field['help'] }}</p>
                            </div>
                        @endforeach
                    </div><div class="admin-settings-save"><span><x-icon name="shield" :size="15" /> 配置修改记录到操作日志</span><button class="btn btn-primary" type="submit">保存{{ $definition['title'] }}</button></div></form>
                </div></section>
            @endif
            @if ($section === 'plugins')
                <div class="admin-integration-cards"><a href="{{ route('admin.settings.index', ['section' => 'controller']) }}"><x-icon name="network" /><b>主控与节点</b><span>管理本地计算资源与节点接入</span></a><a href="{{ route('admin.workspace', ['area' => 'catalog', 'tab' => 'sourcing']) }}"><x-icon name="cloud" /><b>资源供应商</b><span>供应商认证、选品与订单同步</span></a><a href="{{ route('admin.workspace', ['area' => 'catalog', 'tab' => 'panel']) }}"><x-icon name="server" /><b>游戏面板</b><span>翼龙连接及游戏商品导入</span></a></div>
            @endif
            @if ($settingsPartial)
                <div class="admin-merged-content" data-merged-page>@include($settingsPartial, $settingsData)</div>
            @elseif ($section === 'system')
                <section class="panel"><div class="panel-head"><h2>当前运行配置</h2><span class="meta">读取实际运行环境</span></div><div class="panel-body"><dl class="admin-runtime-grid">@foreach ($runtime as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value ?: '未配置' }}</dd></div>@endforeach</dl><div class="note info mt-4">域名、数据库、API 证书与后台任务由服务器部署管理。这里展示实际生效值，不提供会破坏运行环境的占位输入框。</div></div></section>
                <div class="admin-integration-cards mt-4"><a href="{{ route('admin.settings.index', ['section' => 'changes']) }}"><x-icon name="check" /><b>变更审批</b><span>附件配额敏感变更记录</span></a><a href="{{ route('admin.settings.index', ['section' => 'audit']) }}"><x-icon name="activity" /><b>操作日志</b><span>管理员关键操作留痕</span></a></div>
            @endif
        </div>
    </div>
</div>
@endsection
