@extends('layouts.admin')

@section('title', '插件管理')
@section('page-title', '插件管理')

@section('content')
    <div class="page-head">
        <div><h1>插件管理</h1><p>支付、通知、实名与上游 API 对接。配置密钥加密存储。</p></div>
        <span class="chip">插件运行时 · 配置沙箱</span>
    </div>

    <div class="grid-2">
        @foreach ($plugins as $plugin)
            <div class="panel">
                <div class="panel-head">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon" style="width:36px;height:36px;border-radius:9px;display:grid;place-items:center;background:var(--dt-b-eff6ff,#eff6ff);color:var(--dt-t-2563eb,#2563eb)"><x-icon :name="$plugin->type === 'payment' ? 'card' : ($plugin->type === 'upstream' ? 'cloud' : 'plug')" :size="18" /></div>
                        <div><h2>{{ $plugin->name }}</h2><div class="small muted">{{ $plugin->code }} · v{{ $plugin->version }}</div></div>
                    </div>
                    <x-status :status="$plugin->is_enabled ? 'active' : 'pending'" :label="$plugin->is_enabled ? '已启用' : '未启用'" />
                </div>
                <div class="panel-body">
                    <p class="small muted" style="line-height:1.7;min-height:40px">{{ $plugin->description }}</p>
                    <div class="flex items-center justify-between mt-3">
                        <span class="chip">{{ ['payment' => '支付', 'upstream' => '上游', 'notification' => '通知'][$plugin->type] ?? $plugin->type }}</span>
                        <div class="flex items-center gap-2">
                            @if ($plugin->code === 'pterodactyl')
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.pterodactyl.index') }}">配置</a>
                            @endif
                            @if ($plugin->code === 'mail')
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.mail.index') }}">配置</a>
                            @endif
                            <form method="POST" action="{{ route('admin.plugins.toggle', $plugin) }}">
                                @csrf
                                <button class="btn {{ $plugin->is_enabled ? 'btn-ghost' : 'btn-primary' }} btn-sm" type="submit">{{ $plugin->is_enabled ? '停用' : '启用' }}</button>
                            </form>
                        </div>
                    </div>
                    @if ($plugin->is_enabled)
                        <div class="note info mt-3">已启用。生产环境请在配置页维护密钥，密钥会加密存储。</div>
                    @else
                        <div class="note mt-3">未启用，不影响主流程。启用前请确认已完成配置和连接测试。</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
