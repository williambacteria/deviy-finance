@extends($adminView ? 'layouts.admin' : 'layouts.console')
@section('title','服务器托管 · '.$service->name)
@section('page-title','服务器托管')
@section('content')
@include('partials.service-workspace-style')
@php
    $monthlyRenewal = \App\Support\Colocation::monthlyRenewal($service);
    $ssh = data_get($service->specs, 'colocation.ssh');
@endphp
<div class="dv-service-workspace" data-colocation-service>
<div class="page-head"><div><h1>{{ $service->name }}</h1><p>{{ $service->service_no }} · {{ data_get($service->specs,'colocation.datacenter',$service->region) }}</p></div><div class="page-actions"><span class="chip">{{ $service->status==='terminated'?'托管已结束':($service->status==='pending'?'待交付':($service->expires_at?->isPast()?'已到期 · 请联系续期':'托管中')) }}</span></div></div>
<div class="grid-2"><div class="panel"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="calendar" :size="20" /></span><span class="dv-card-caption"><span>托管有效期</span><small>机房服务期限与续期安排</small></span></h2></div><div class="panel-body"><div style="font-size:30px;font-weight:700;margin:8px 0 14px">{{ $service->expires_at?->format('Y-m-d') ?: '待确认' }}</div><p>到期当日 {{ $service->expires_at?->format('H:i') }}（北京时间）</p>
@if ($monthlyRenewal)
    <p>月续费金额 <strong>{{ $monthlyRenewal }}</strong></p>
@endif
<p class="muted small">客户自有设备托管。续期、设备维护与现场协助请联系机房服务人员。</p>@if(!$adminView)<a class="btn btn-primary" href="{{ route('console.tickets.create') }}">联系续期 / 提交工单</a>@else <p>所属客户：{{ $service->user?->name }} · {{ $service->user?->email }}</p>@endif</div></div>
<div class="panel"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="building" :size="20" /></span><span class="dv-card-caption"><span>机房与网络</span><small>托管位置及网络接入信息</small></span></h2></div><div class="panel-body"><p>{{ data_get($service->specs,'colocation.datacenter',$service->region) }}</p><label class="form-label">公网 IP</label><div style="display:flex;align-items:center;gap:12px"><b class="mono" id="colocation-address">{{ $service->ip_address ?: '未登记' }}</b>@if($service->ip_address)<button type="button" class="btn btn-sm" data-copy-colocation>复制</button>@endif</div><p style="margin-top:18px">上行 {{ \App\Support\Colocation::bandwidth($service, 'upload') }} / 下行 {{ \App\Support\Colocation::bandwidth($service, 'download') }}</p><p class="small muted">托管设备不提供云服务器的开关机、重装、端口映射及监控接口。</p></div></div></div>
@if ($ssh && ! $adminView)
    <section class="panel">
        <div class="panel-head">
            <h2><span class="dv-card-symbol"><x-icon name="terminal-connect" :size="20" /></span><span class="dv-card-caption"><span>远程连接</span><small>托管设备的 SSH 登录信息</small></span></h2>
        </div>
        <div class="panel-body">
            <dl class="grid-2" style="margin:0">
                <div><dt class="muted small">连接地址</dt><dd class="mono" style="margin:6px 0 0;overflow-wrap:anywhere">{{ $service->ip_address ? $service->ip_address.':'.$ssh['port'] : '未登记' }}</dd></div>
                <div><dt class="muted small">远程协议</dt><dd style="margin:6px 0 0">{{ $ssh['protocol'] }}</dd></div>
                <div><dt class="muted small">登录账号</dt><dd class="mono" style="margin:6px 0 0">{{ $ssh['username'] }}</dd></div>
                @if (! empty($sshPassword))
                    <div>
                        <dt class="muted small">登录密码</dt>
                        <dd style="margin:6px 0 0">
                            <details>
                                <summary class="btn btn-sm" style="cursor:pointer">查看密码</summary>
                                <code class="mono" style="display:block;margin-top:8px;overflow-wrap:anywhere">{{ $sshPassword }}</code>
                            </details>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>
@endif
<div class="panel"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="hardware-chip" :size="20" /></span><span class="dv-card-caption"><span>设备登记信息</span><small>机位、硬件和网络配置</small></span></h2></div><div class="panel-body"><dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:22px;margin:0">@foreach(\App\Support\Colocation::fields($service) as $label=>$value)<div><dt class="muted small">{{ $label }}</dt><dd style="margin:7px 0 0;font-weight:600;overflow-wrap:anywhere">{{ $value }}</dd></div>@endforeach</dl></div></div>
@if($adminView && in_array(auth('admin')->user()->role,['super_admin','support'],true))<div class="panel"><div class="panel-head"><h2><span class="dv-card-symbol"><x-icon name="edit" :size="20" /></span><span class="dv-card-caption"><span>维护托管资料</span><small>保存设备信息与到期时间</small></span></h2></div><form method="POST" class="panel-body" action="{{ route('admin.services.colocation.update',$service) }}">@csrf @method('PUT')<div class="grid-2">@foreach(['name'=>'资源名称','cpu_model'=>'CPU 型号','memory_gb'=>'内存（GB）','gpu_model'=>'显卡型号与数量','datacenter'=>'托管机房','upload_mbps'=>'上行带宽（Mbps）','download_mbps'=>'下行带宽（Mbps）','ip_address'=>'公网 IP'] as $key=>$label)<label class="field"><span>{{ $label }}</span><input class="input" name="{{ $key }}" value="{{ old($key,in_array($key,['name','ip_address'])?$service->$key:($key==='datacenter'?data_get($service->specs,'colocation.datacenter'):data_get($service->specs,$key))) }}" @if($key!=='gpu_model'&&$key!=='ip_address') required @endif></label>@endforeach<label class="field"><span>机位规格</span><select name="rack_units" class="select">@foreach([1,2,4] as $unit)<option value="{{ $unit }}" @selected((int)data_get($service->specs,'colocation.rack_units')===$unit)>{{ $unit }}U</option>@endforeach<option value="tower" @selected(data_get($service->specs, 'colocation.form_factor') === '塔式')>塔式</option></select></label><label class="field"><span>到期日期</span><input class="input" type="date" name="expires_at" value="{{ $service->expires_at?->format('Y-m-d') }}" required></label><label class="field"><span>托管状态</span><select name="status" class="select">@foreach(['active'=>'托管中','pending'=>'待交付','terminated'=>'托管已结束'] as $value=>$label)<option value="{{ $value }}" @selected($service->status===$value)>{{ $label }}</option>@endforeach</select></label></div><button type="submit" class="btn btn-primary" style="margin-top:20px">保存托管资料</button></form></div>@endif
</div>
@endsection
@push('scripts')<script nonce="{{ $cspNonce??'' }}">document.querySelector('[data-copy-colocation]')?.addEventListener('click',async function(){const el=document.getElementById('colocation-address');try{await navigator.clipboard.writeText(el.textContent.trim());this.textContent='已复制';}catch(e){const r=document.createRange();r.selectNodeContents(el);const s=window.getSelection();s.removeAllRanges();s.addRange(r);this.textContent='已选中，请复制';}});</script>@endpush
