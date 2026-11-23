@extends('layouts.console')
@section('title','代理与 API')
@section('page-title','代理与 API')
@section('content')
@include('console.reseller.style')
<header class="api-hero"><div><small>DEVIY CLOUD · PARTNER API</small><h1>让云业务，接入你的平台。</h1><p>申请代理 API，将本站商品接入下游财务系统。商品、订单、余额与实例统一管理，支持魔方财务 zjmf_api 资源接口。</p></div><a class="btn" href="{{ route('console.reseller.docs') }}">查看 API 文档 ↗</a></header>
<div class="api-grid"><div>
<section class="api-card"><h2><x-icon name="plug" /> API 开通申请 @if($account)<span class="api-status">{{ ['pending'=>'审核中','approved'=>'已开通','rejected'=>'未通过','suspended'=>'已停用'][$account->status]??$account->status }}</span>@endif</h2>
@if(!$account || $account->status==='rejected')
@if($account?->review_note)<p class="api-help">审核说明：{{ $account->review_note }}</p>@endif
<form method="POST" action="{{ route('console.reseller.store') }}">@csrf
<div class="api-form-row"><div><label for="partner-site">平台名称</label><input id="partner-site" name="site_name" maxlength="100" required value="{{ old('site_name',$account?->site_name) }}" placeholder="你的云服务品牌"></div><div><label for="partner-url">平台网址</label><input id="partner-url" name="site_url" type="url" maxlength="255" required value="{{ old('site_url',$account?->site_url) }}" placeholder="https://example.com"></div></div>
<label for="partner-purpose">业务用途与预计规模</label><textarea id="partner-purpose" name="purpose" minlength="10" maxlength="1000" required placeholder="例如：使用魔方财务代理云服务器，预计每月开通20台。">{{ old('purpose',$account?->purpose) }}</textarea>
<p class="api-help">申请前请完成邮箱验证及实名认证。通过后可自行生成独立 API 密钥；消费使用本站账户余额，价格以接口返回的商品与配置价格为准。</p><div class="api-actions"><button class="btn btn-primary" type="submit">提交 API 申请</button><a class="btn btn-ghost" href="{{ route('console.reseller.docs') }}">先阅读接口文档</a></div></form>
@else
<dl class="api-info"><dt>平台名称</dt><dd>{{ $account->site_name }}</dd><dt>平台网址</dt><dd>{{ $account->site_url }}</dd><dt>审核说明</dt><dd>{{ $account->review_note ?: ($account->status==='pending'?'申请已收到，请等待管理员审核。':'—') }}</dd><dt>申请时间</dt><dd>{{ $account->created_at->format('Y-m-d H:i') }}</dd></dl>
@endif
</section>
@if($account?->status==='approved')
<section class="api-card"><h2><x-icon name="key" /> 接入凭据</h2>
@if(session('api_secret'))<div class="api-secret"><b>新密钥仅显示本次，请立即保存</b><code id="new-api-secret">{{ session('api_secret') }}</code><button class="btn btn-sm" type="button" data-api-copy="new-api-secret">复制密钥</button></div>@endif
<dl class="api-info"><dt>接口地址</dt><dd><span id="api-base">{{ rtrim(config('app.url'),'/') }}</span> <button class="btn btn-sm" type="button" data-api-copy="api-base">复制</button></dd><dt>API 账号</dt><dd><span id="api-username">{{ auth()->user()->email }}</span> <button class="btn btn-sm" type="button" data-api-copy="api-username">复制</button></dd><dt>API 密钥</dt><dd>{{ $account->secret_hint?'已设置 · 尾号 '.$account->secret_hint:'尚未生成' }}</dd><dt>兼容方式</dt><dd>魔方财务 → 魔方财务 API → zjmf_api</dd></dl>
<form method="POST" action="{{ route('console.reseller.update') }}">@csrf @method('PUT')<input type="hidden" name="action" value="rotate"><label>确认账户登录密码</label><input type="password" name="current_password" autocomplete="current-password" required><p class="api-help">填写本站登录密码确认操作。生成或重置后，旧密钥和已签发 Token 立即失效，请同步更新下游配置。</p><div class="api-actions"><button class="btn btn-primary" type="submit">{{ $account->secret_hint?'重置 API 密钥':'生成 API 密钥' }}</button></div></form>
</section>
<section class="api-card"><h2><x-icon name="shield" /> IP 访问白名单</h2><form method="POST" action="{{ route('console.reseller.update') }}">@csrf @method('PUT')<input type="hidden" name="action" value="whitelist"><label>允许的服务器 IP</label><textarea name="ip_allowlist" placeholder="每行一个 IPv4 或 IPv6；留空不限制 IP">{{ implode("\n",$account->ip_allowlist??[]) }}</textarea><label>确认账户登录密码</label><input type="password" name="current_password" required autocomplete="current-password"><p class="api-help">填写下游服务器的出口公网 IP。保存后对密钥登录和 Token 请求同时生效。</p><div class="api-actions"><button type="submit" class="btn">保存白名单</button></div></form></section>
@endif
</div><aside><section class="api-card"><h2><x-icon name="file" /> 接口文档</h2><p>这里提供完整的鉴权、商品同步、下单结算、实例管理及续费说明，可在申请前阅读。</p><ol class="api-steps"><li><b>申请并完成审核</b><div class="api-help">后台确认后开通 API 权限。</div></li><li><b>生成独立密钥</b><div class="api-help">接口账号为本站邮箱；API 密钥与登录密码分离。</div></li><li><b>配置魔方财务</b><div class="api-help">填入接口地址、API 账号和 API 密钥，拉取商品后设置你的销售价格。</div></li><li><b>充值并开始销售</b><div class="api-help">订单按本站金额扣减余额，开通进度通过实例接口查询。</div></li></ol><div class="api-actions"><a class="btn btn-primary" href="{{ route('console.reseller.docs') }}">在线接口文档</a><a class="btn" href="{{ route('console.reseller.docs',['download'=>1]) }}">下载 Markdown</a></div></section>
<section class="api-card"><h2><x-icon name="wallet" /> 结算与权限</h2><p>使用当前账户余额结算，无独立充值账户。当前可用余额 <b>¥{{ number_format((float)auth()->user()->balance,2) }}</b>。</p><p class="api-help">仅开放在售商品；迁移专用隐藏商品不会被同步。每个代理只能查询及管理自己名下的实例和账单。CDN 不提供服务器电源、远程登录或重装系统操作。</p><a class="btn btn-ghost" href="{{ route('console.recharge.index') }}">账户充值</a></section></aside></div>
@if($account)<section class="api-card"><h2><x-icon name="activity" /> 最近 API 调用</h2><div class="api-log-wrap"><table class="table"><thead><tr><th>时间</th><th>请求</th><th>IP</th><th>状态码</th><th>追踪编号</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->method }} {{ $log->path }}</td><td>{{ $log->ip }}</td><td>{{ $log->status }}</td><td>{{ $log->request_id }}</td></tr>@empty<tr><td colspan="5" class="muted">暂无调用记录</td></tr>@endforelse</tbody></table></div><p class="api-help">日志不记录 API 密钥、Token 或请求正文。</p></section>@endif
@endsection
