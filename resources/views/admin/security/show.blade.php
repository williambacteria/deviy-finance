@extends('layouts.admin')

@section('title', '账户安全')
@section('page-title', '账户安全')

@section('content')
    <div class="page-head">
        <div><h1>账户安全</h1><p>为管理员账户启用 TOTP 双重验证，降低密码泄露风险。</p></div>
        <div class="page-actions"><a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">返回概览</a></div>
    </div>

    @if ($errors->any())
        <div class="note warn" style="margin-bottom:16px">{{ $errors->first() }}</div>
    @endif

    @if (session('recovery_codes'))
        <div class="panel" style="border-color:var(--dt-e-f59e0b,#f59e0b);margin-bottom:18px">
            <div class="panel-head"><h2>恢复码（只显示一次）</h2></div>
            <div class="panel-body">
                <p class="small muted">请立即保存到密码管理器或离线位置。每枚恢复码只能使用一次。</p>
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px">
                    @foreach (session('recovery_codes') as $code)
                        <code style="background:var(--dt-b-0f172a,#0f172a);color:var(--dt-t-e2e8f0,#e2e8f0);padding:8px 10px;border-radius:6px">{{ $code }}</code>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="panel" style="max-width:760px">
        <div class="panel-head">
            <h2>双重验证（TOTP）</h2>
            @if ($admin->two_factor_confirmed_at)
                <span class="badge active">已启用</span>
            @else
                <span class="badge">未启用</span>
            @endif
        </div>
        <div class="panel-body">
            @if ($admin->two_factor_confirmed_at)
                <p class="small muted">当前账户已启用双重验证。关闭后仅使用密码登录，建议仅在更换设备时临时关闭。</p>
                <form method="POST" action="{{ route('admin.security.disable') }}" style="margin-top:16px">
                    @csrf @method('DELETE')
                    <div class="form-field" style="max-width:320px">
                        <label class="form-label" for="password">当前密码</label>
                        <input class="input" id="password" name="password" type="password" required>
                    </div>
                    <button class="btn btn-danger mt-4" type="submit" data-confirm="确认关闭双重验证？">关闭双重验证</button>
                </form>
            @else
                <p class="small muted">在 Google Authenticator、1Password、Microsoft Authenticator 等验证器中手动添加以下密钥，然后输入 6 位动态码确认。</p>
                <div style="margin-top:16px;padding:14px;background:var(--dt-b-f8fafc,#f8fafc);border:1px solid var(--dt-e-e2e8f0,#e2e8f0);border-radius:8px">
                    <div class="small muted">手动密钥</div>
                    <code style="font-size:16px;letter-spacing:2px;word-break:break-all">{{ $setupSecret }}</code>
                    <div class="small muted" style="margin-top:12px">otpauth URI</div>
                    <code class="small" style="word-break:break-all">{{ $provisioningUri }}</code>
                </div>
                <form method="POST" action="{{ route('admin.security.enable') }}" style="margin-top:16px">
                    @csrf
                    <div class="form-field" style="max-width:320px">
                        <label class="form-label" for="code">验证器 6 位动态码</label>
                        <input class="input" id="code" name="code" inputmode="numeric" maxlength="6" placeholder="000000" required>
                    </div>
                    <button class="btn btn-primary mt-4" type="submit">启用双重验证</button>
                </form>
            @endif
        </div>
    </div>
@endsection
