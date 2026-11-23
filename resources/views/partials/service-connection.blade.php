@php
    $connectionEndpoint = \App\Support\RemoteEndpoint::forService($service);
    $connectionRemote = (array) data_get($service->specs, 'remote', []);
    $connectionWindows = str_contains(strtolower((string) $service->os), 'windows');
    $connectionProtocol = strtoupper((string) ($connectionRemote['protocol'] ?? ($connectionWindows ? 'rdp' : 'ssh')));
    $connectionUsername = trim((string) ($connectionRemote['username'] ?? '')) ?: ($connectionWindows ? 'Administrator' : 'root');
    $connectionPassword = (string) ($connectionRemote['password'] ?? '');
    $connectionAdmin = $connectionAdmin ?? false;
    $connectionResetAllowed = ($connectionResetAllowed ?? true) && in_array($service->status, ['active', 'stopped', 'suspended'], true) && ! data_get($service->specs, 'legacy_import.operation_hold', false);
    $connectionNeedsShutdown = $service->product?->upstream_source === 'mofang';
    $connectionId = 'connection-reset-'.$service->id.'-'.($connectionAdmin ? 'admin' : 'client');
@endphp
@pushOnce('styles', 'service-connection-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/service-connection.css') }}?v={{ filemtime(public_path('assets/css/service-connection.css')) }}">
@endPushOnce
@pushOnce('scripts', 'service-connection-script')
    <script src="{{ asset('assets/js/service-connection.js') }}?v={{ filemtime(public_path('assets/js/service-connection.js')) }}"></script>
@endPushOnce

<section class="panel dv-connection" data-connection-card>
    <div class="dv-connection-head">
        <div class="dv-connection-heading"><span class="dv-connection-symbol"><x-icon name="terminal-connect" :size="20" /></span><div><h2>远程连接</h2><p>连接信息自动同步，复制后即可使用</p></div></div>
        <span class="dv-protocol">{{ $connectionProtocol }}</span>
    </div>
    <div class="dv-connection-body">
        <div class="dv-endpoint">
            <span class="dv-field-label">连接地址</span>
            <div class="dv-endpoint-line"><strong class="mono" data-connection-address>{{ $connectionEndpoint['display'] ?: '连接信息待同步' }}</strong><button class="dv-copy-btn" type="button" data-connection-copy="{{ $connectionEndpoint['display'] }}" aria-label="复制连接地址" @disabled($connectionEndpoint['display'] === '')><x-icon name="copy" :size="16" /><span>复制地址</span></button></div>
        </div>
        <div class="dv-credentials">
            <div class="dv-credential"><label class="dv-field-label">登录账号</label><div class="dv-value-line"><span class="mono" data-connection-username>{{ $connectionUsername }}</span><button class="dv-mini-btn" type="button" data-connection-copy="{{ $connectionUsername }}" aria-label="复制登录账号"><x-icon name="copy" :size="16" /><span>复制</span></button></div></div>
            <div class="dv-credential"><label class="dv-field-label">登录密码</label><div class="dv-value-line"><input class="dv-secret mono" type="password" value="{{ $connectionPassword }}" readonly aria-label="登录密码" autocomplete="off" data-connection-secret placeholder="密码待同步"><div class="dv-secret-actions"><button class="dv-mini-btn" type="button" data-connection-reveal aria-label="显示密码" aria-pressed="false" @disabled($connectionPassword === '')><x-icon name="eye" :size="17" /><span>显示</span></button><button class="dv-mini-btn" type="button" data-connection-copy="{{ $connectionPassword }}" aria-label="复制登录密码" @disabled($connectionPassword === '')><x-icon name="copy" :size="16" /><span>复制</span></button></div></div></div>
        </div>
        <div class="dv-connection-footer"><span><x-icon name="shield" :size="15" /> 请妥善保管连接信息</span><div class="dv-footer-actions">@if ($connectionWindows && ! $connectionAdmin && $connectionEndpoint['display'] !== '')<a class="dv-download" href="{{ route('console.services.rdp', $service) }}"><x-icon name="download" :size="15" /> 下载 RDP 文件</a>@endif @if ($connectionResetAllowed)<button class="dv-reset-trigger" type="button" data-connection-open="{{ $connectionId }}"><x-icon name="refresh" :size="15" /> 重置密码</button>@endif</div></div>
        <div class="dv-copy-feedback" data-connection-feedback role="status" aria-live="polite"></div>
    </div>
</section>

@if ($connectionResetAllowed)
<dialog id="{{ $connectionId }}" class="dv-reset-dialog" data-connection-dialog>
    <form method="POST" action="{{ route(($connectionAdmin ? 'admin' : 'console').'.services.password', $service) }}" data-connection-reset-form>
        @csrf
        <div class="dv-reset-head"><div><h3>重置登录密码</h3><p>为当前实例设置新的连接密码</p></div><button class="dv-dialog-close" type="button" data-connection-close aria-label="关闭重置密码窗口">×</button></div>
        <div class="dv-reset-body"><label class="dv-field-label" for="{{ $connectionId }}-password">新密码</label><div class="dv-password-input"><input id="{{ $connectionId }}-password" type="password" name="password" minlength="8" maxlength="128" required autocomplete="new-password" placeholder="输入至少 8 位的新密码" data-connection-new-password><button type="button" class="dv-mini-btn" data-connection-new-reveal aria-label="显示新密码">显示</button></div><div class="dv-generate-row"><span>建议混合使用大小写字母、数字和符号</span><button type="button" class="dv-generate" data-connection-generate><x-icon name="refresh" :size="14" /> 生成密码</button></div>
            @if ($connectionNeedsShutdown)<div class="dv-shutdown-note"><x-icon name="alert" :size="18" /><p>重置密码可能需要强制关机，会中断当前业务。请先保存工作并做好备份。</p></div><label class="dv-shutdown-check"><input type="checkbox" name="force_shutdown" value="1" required> 我已了解并同意强制关机后重置密码</label>@endif
        </div>
        <div class="dv-reset-footer"><button class="btn btn-ghost" type="button" data-connection-close>取消</button><button class="btn btn-primary" type="submit">确认重置</button></div>
    </form>
</dialog>
@endif
