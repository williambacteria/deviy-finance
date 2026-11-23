@extends('layouts.admin')

@section('title', $customer->name)
@section('page-title', '客户详情')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/customer-resources.css') }}?v={{ filemtime(public_path('assets/css/customer-resources.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/service-adjust.css') }}?v={{ filemtime(public_path('assets/css/service-adjust.css')) }}">
@endpush

@section('content')
    @php
        $customerPresence = $customer->status !== 'active' ? 'disabled' : (($customer->last_seen_at && $customer->last_seen_at->gte(now()->subMinutes(5))) ? 'online' : 'offline');
        $customerPresenceLabel = ['online' => '在线', 'offline' => '离线', 'disabled' => '停用'][$customerPresence];
    @endphp
    <div class="page-head">
        <div>
            <div class="flex items-center gap-3"><h1>{{ $customer->name }}</h1><x-status :status="$customerPresence" :label="$customerPresenceLabel" /><span class="chip">{{ $customer->customer_group === 'vip' ? 'VIP' : '标准' }}</span></div>
            <p>{{ $customer->email }} · {{ $customer->phone ?: '未填写手机' }} · QQ {{ $customer->qq ?: '未填写' }} · 注册于 {{ $customer->created_at?->format('Y-m-d') }}</p>
        </div>
        <div class="page-actions">
            <form method="POST" action="{{ route('admin.customers.impersonate', $customer) }}" style="display:inline">
                @csrf
                <button class="btn btn-soft" type="submit"><x-icon name="user" :size="15" /> 以客户身份登录</button>
            </form>
            <button class="btn btn-danger" type="button" data-delete-customer data-has-services="{{ $customer->services_count > 0 ? '1' : '0' }}">删除客户</button>
            <button class="btn btn-primary" type="button" id="open-service-modal">+ 添加服务</button>
            <a class="btn btn-ghost" href="{{ route('admin.customers.index') }}">返回客户列表</a>
        </div>
    </div>

    @if ($customer->real_name_status === 'pending')
        <div class="note" style="margin-bottom:14px;display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:260px">
                @if ($customer->real_name_type === 'enterprise')
                    <b>企业实名待审核</b>
                    <div>{{ $customer->real_name_company }} · {{ $customer->real_name_credit_code }}</div>
                    <div>法人：{{ $customer->real_name_legal_name }} · {{ $customer->real_name_legal_phone }}</div>
                    <div style="margin-top:6px;display:flex;gap:12px;flex-wrap:wrap">
                        <a href="{{ route('admin.customers.real-name.file', [$customer, 'license']) }}" target="_blank">查看营业执照</a>
                        <a href="{{ route('admin.customers.real-name.file', [$customer, 'legal_id']) }}" target="_blank">查看法人身份证</a>
                    </div>
                @else
                    <b>个人实名待审核</b>
                    <div>{{ $customer->real_name }} · {{ $customer->maskedRealNameId() }} · {{ $customer->real_name_phone }}</div>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.customers.real-name.verify', $customer) }}" style="display:inline">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit">通过实名</button>
            </form>
            <form method="POST" action="{{ route('admin.customers.real-name.reject', $customer) }}" class="filter-bar" style="display:inline-flex;gap:6px">
                @csrf
                <input class="input" name="reason" placeholder="驳回原因" required style="min-width:180px">
                <button class="btn btn-danger btn-sm" type="submit">驳回</button>
            </form>
        </div>
    @elseif ($customer->real_name_status === 'rejected')
        <div class="note danger" style="margin-bottom:14px">实名认证已驳回：{{ $customer->real_name_reject_reason ?: '资料不完整' }}</div>
    @elseif ($customer->real_name_status !== 'verified')
        <div class="note" style="margin-bottom:14px">该客户尚未提交实名认证，完成前无法购买商品。</div>
    @endif

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-top"><span class="stat-label">账户余额</span><span class="stat-icon"><x-icon name="wallet" :size="17" /></span></div><div class="stat-value">{{ \App\Support\Money::format($customer->balance) }}</div><div class="stat-foot">信用额度 {{ \App\Support\Money::format($customer->credit_limit) }}</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">服务</span><span class="stat-icon"><x-icon name="server" :size="17" /></span></div><div class="stat-value">{{ $customer->services_count }}</div><div class="stat-foot">含待开通</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">订单</span><span class="stat-icon"><x-icon name="receipt" :size="17" /></span></div><div class="stat-value">{{ $customer->orders_count }}</div><div class="stat-foot">历史订单</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">工单</span><span class="stat-icon"><x-icon name="ticket" :size="17" /></span></div><div class="stat-value">{{ $customer->tickets_count }}</div><div class="stat-foot">全部工单</div></div>
    </div>

    @php
        $cycleLabels = ['monthly' => '月付', 'quarterly' => '季付', 'semiannual' => '半年付', 'annual' => '年付'];
        $groupedProducts = $products->groupBy(fn ($product) => $product->group?->parent
            ? $product->group->parent->name.' / '.$product->group->name
            : ($product->group?->name ?? '未分组'));
    @endphp
    <div class="modal" id="service-modal" aria-hidden="true" data-errors="{{ ! old('renewal_mode') && ! old('confirm_service_no') && $errors->hasAny(['product_id', 'billing_cycle', 'first_price', 'renewal_price', 'balance', 'ip_mode', 'nat_ports', 'data_disk_gb', 'ostype', 'node_id', 'region', 'ip_address', 'bandwidth_mbps', 'auto_renew', 'upstream_os', 'upstream_config']) ? '1' : '0' }}">
        <div class="modal-card">
            <div class="modal-head"><h2>为客户开通商品</h2><button class="modal-close" type="button" id="close-service-modal">×</button></div>
            <div class="modal-body">
                <div class="note">按客户购买流程生成已支付订单、账单和服务；首付将从客户账户余额扣除。</div>
                @if ($errors->has('balance'))
                    <div class="note danger" style="margin-top:10px">{{ $errors->first('balance') }}</div>
                @endif
                <form method="POST" action="{{ route('admin.customers.services.store', $customer) }}" class="mt-3" data-service-purchase-form data-initial-cycle="{{ old('billing_cycle') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-field"><label class="form-label">商品 <span>*</span></label>
                        <select class="select" name="product_id" required data-service-product>
                            <option value="">选择商品</option>
                            @foreach ($groupedProducts as $groupLabel => $groupProducts)
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach ($groupProducts as $product)
                                        <option value="{{ $product->id }}"
                                            data-source="{{ $product->upstream_source ?: data_get($product->metadata, 'upstream', '') }}"
                                            data-type="{{ $product->type }}"
                                            data-controller-node="{{ data_get($product->metadata, 'controller.node_id') }}"
                                            data-prices="{{ $product->prices->mapWithKeys(fn ($price) => [(string) $price->billing_cycle => (float) $price->price])->toJson() }}"
                                            @selected(old('product_id') == $product->id)>{{ $product->name }} · {{ $product->region }} · {{ $product->code }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-field"><label class="form-label">计费周期 <span>*</span></label>
                        <select class="select" name="billing_cycle" required data-service-cycle>
                            <option value="">请先选择商品</option>
                        </select>
                    </div>
                </div>

                <div class="service-price-summary">
                    <div><span>首付价格</span><input class="input service-price-input" type="number" min="0" step="0.01" name="first_price" value="{{ old('first_price') }}" placeholder="0.00" required data-service-first-price></div>
                    <div><span>续费价格</span><input class="input service-price-input" type="number" min="0" step="0.01" name="renewal_price" value="{{ old('renewal_price') }}" placeholder="0.00" required data-service-renewal-price></div>
                    <div class="small muted">价格按商品当前计费周期预填，可手动修改；续费价格按当前周期写入服务。</div>
                </div>

                @foreach ($products as $product)
                    @php
                        $isMofang = $product->upstream_source === 'mofang' || data_get($product->metadata, 'upstream') === 'mofang';
                        $isPterodactyl = $product->upstream_source === 'pterodactyl';
                        $controllerNodeId = trim((string) data_get($product->metadata, 'controller.node_id'));
                        $pterodactyl = is_array($product->metadata['pterodactyl'] ?? null) ? $product->metadata['pterodactyl'] : [];
                        $uploadValues = collect((array) data_get($product->metadata, 'options.upload_mbps', []))
                            ->pluck('value')
                            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0);
                        $defaultUpload = $uploadValues->isNotEmpty()
                            ? (int) $uploadValues->min()
                            : (int) data_get($product->metadata, 'specs.upload_mbps', data_get($product->metadata, 'specs.bandwidth_mbps', 10));
                        $defaultDataDisk = (int) data_get($product->metadata, 'specs.data_disk_gb', 0);
                        $natPortValues = collect((array) data_get($product->metadata, 'options.nat_ports', []))
                            ->pluck('value')
                            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0);
                        $defaultNatPorts = $natPortValues->isNotEmpty()
                            ? (int) $natPortValues->min()
                            : (int) data_get($product->metadata, 'specs.nat_ports', 20);
                        $osOptions = \App\Support\ProductOptions::options($product)['ostype'] ?? [];
                        $defaultOs = $osOptions[0]['value'] ?? 'l26';
                    @endphp
                    <div class="service-product-panel" data-product-panel="{{ $product->id }}" hidden>
                        @if ($isMofang)
                            <div class="service-readonly-note">
                                <b>魔方上游商品</b>
                                <span>商品 #{{ data_get($product->metadata, 'upstream_id', '—') }} · 上游自动开通，无需绑定主控</span>
                            </div>
                            <div class="form-grid">
                                <div class="form-field"><label class="form-label">上游系统</label>
                                    <select class="select" name="upstream_os">
                                        <option value="">使用上游默认系统</option>
                                        @foreach ((array) data_get($product->metadata, 'upstream_os_groups', []) as $osGroup)
                                            @foreach ((array) ($osGroup['versions'] ?? []) as $version)
                                                <option value="{{ ($osGroup['family'] ?? '').'|'.($version['label'] ?? '') }}" @selected(old('upstream_os') === (($osGroup['family'] ?? '').'|'.($version['label'] ?? '')))>{{ $osGroup['family'] ?? '' }} / {{ $version['label'] ?? '' }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field"><label class="form-label">上游配置</label><input class="input" value="使用商品默认配置" disabled></div>
                            </div>
                        @elseif ($isPterodactyl)
                            <div class="service-readonly-note">
                                <b>翼龙面板商品</b>
                                <span>Nest #{{ $pterodactyl['nest_id'] ?? '—' }} / Egg #{{ $pterodactyl['egg_id'] ?? '—' }} / Node #{{ $pterodactyl['node_id'] ?? '—' }}</span>
                            </div>
                            <p class="small muted" style="margin:0">开通后自动创建面板用户和服务器，无需填写宿主机或 IP。</p>
                        @else
                            <div class="form-grid">
                                @if ($controllerNodeId === '')
                                    <div class="form-field"><label class="form-label">宿主机 <span>*</span></label>
                                        <select class="select" name="node_id" required>
                                            <option value="">选择宿主机节点</option>
                                            @foreach ($nodes as $node)
                                                <option value="{{ $node->id }}" @selected(old('node_id') == $node->id)>{{ $node->name }} · {{ strtoupper($node->provider) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <div class="form-field"><label class="form-label">宿主机</label><input class="input" value="{{ $controllerNodeId }}" disabled><span class="small muted">使用商品绑定的主控节点</span></div>
                                @endif
                                <div class="form-field"><label class="form-label">地区</label><input class="input" name="region" value="{{ old('region', $product->region) }}" placeholder="留空使用商品地区"></div>
                                <div class="form-field"><label class="form-label">IP 类型</label>
                                    <select class="select" name="ip_mode" data-ip-mode>
                                        <option value="shared" @selected(old('ip_mode', 'shared') === 'shared')>共享 IP（默认，NAT）</option>
                                        <option value="dedicated" @selected(old('ip_mode') === 'dedicated')>独立 IP</option>
                                    </select>
                                </div>
                                <div class="form-field" data-ip-field @if (old('ip_mode', 'shared') !== 'dedicated') hidden @endif><label class="form-label">IP 地址</label><input class="input" name="ip_address" value="{{ old('ip_address') }}" placeholder="留空自动分配独立 IP" @if (old('ip_mode', 'shared') !== 'dedicated') disabled @endif></div>
                                <div class="form-field"><label class="form-label">带宽 Mbps</label><input class="input" type="number" name="bandwidth_mbps" min="1" max="10000" value="{{ old('bandwidth_mbps', $defaultUpload) }}"></div>
                                <div class="form-field"><label class="form-label">数据盘 GB</label><input class="input" type="number" name="data_disk_gb" min="0" max="65536" value="{{ old('data_disk_gb', $defaultDataDisk) }}"></div>
                                <div class="form-field"><label class="form-label">操作系统</label>
                                    <select class="select" name="ostype">
                                        @foreach ($osOptions as $osOption)
                                            <option value="{{ $osOption['value'] }}" @selected(old('ostype', $defaultOs) === (string) $osOption['value'])>{{ $osOption['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field" data-nat-ports-field @if (old('ip_mode', 'shared') === 'dedicated') hidden @endif><label class="form-label">开放端口数</label><input class="input" type="number" name="nat_ports" min="1" max="1000" value="{{ old('nat_ports', $defaultNatPorts) }}" @if (old('ip_mode', 'shared') === 'dedicated') disabled @endif></div>
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="form-grid" style="margin-top:16px">
                    <div class="form-field"><label class="form-label">自动续费</label><select class="select" name="auto_renew"><option value="0" @selected(old('auto_renew', '0') === '0')>关闭</option><option value="1" @selected(old('auto_renew') === '1')>开启</option></select></div>
                </div>

                <div class="flex items-center gap-3 mt-4">
                    <button class="btn btn-primary" type="submit">为客户开通</button>
                    <button class="btn btn-ghost" type="button" id="cancel-service-modal">取消</button>
                    <span class="small muted">提交后立即生成已支付订单，并按商品类型进入主控、魔方或翼龙开通流程。</span>
                </div>
            </form>
            </div>
        </div>
    </div>

    <div class="grid-2 customer-detail-layout">
        <div style="display:grid;gap:16px">
            <div class="panel">
                <div class="panel-head"><h2>客户资料</h2><span class="meta">{{ $customer->real_name_status === 'verified' ? '已实名' : '未实名' }}</span></div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
                        @csrf @method('PUT')
                        <div class="form-grid">
                            <div class="form-field"><label class="form-label">姓名</label><input class="input" name="name" value="{{ old('name', $customer->name) }}" required></div>
                            <div class="form-field"><label class="form-label">邮箱</label><input class="input" type="email" name="email" value="{{ old('email', $customer->email) }}" required></div>
                            <div class="form-field"><label class="form-label">手机</label><input class="input" name="phone" value="{{ old('phone', $customer->phone) }}"></div>
                            <div class="form-field"><label class="form-label">QQ号</label><input class="input" name="qq" value="{{ old('qq', $customer->qq) }}"></div>
                            <div class="form-field"><label class="form-label">公司</label><input class="input" name="company" value="{{ old('company', $customer->company) }}"></div>
                            <div class="form-field"><label class="form-label">分组</label><select class="select" name="customer_group"><option value="default" @selected($customer->customer_group === 'default')>标准</option><option value="vip" @selected($customer->customer_group === 'vip')>VIP</option></select></div>
                            <div class="form-field"><label class="form-label">状态</label><select class="select" name="status"><option value="active" @selected($customer->status === 'active')>正常</option><option value="suspended" @selected($customer->status === 'suspended')>暂停</option><option value="closed" @selected($customer->status === 'closed')>关闭</option></select></div>
                            <div class="form-field"><label class="form-label">信用额度</label><input class="input" type="number" step="0.01" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}"></div>
                            <div class="form-field full"><label class="form-label">内部备注</label><textarea class="textarea" name="notes" rows="3">{{ old('notes', $customer->notes) }}</textarea></div>
                        </div>
                        <button class="btn btn-primary mt-4" type="submit">保存客户资料</button>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>实名认证</h2><x-status :status="$customer->real_name_status === 'verified' ? 'active' : ($customer->real_name_status === 'pending' ? 'pending' : ($customer->real_name_status === 'rejected' ? 'failed' : 'disabled'))" :label="['verified' => '已认证', 'pending' => '待审核', 'rejected' => '已驳回'][$customer->real_name_status] ?? '未认证'" /></div>
                <div class="panel-body">
                    <div class="profile-facts">
                        <div><span>认证类型</span><b>{{ $customer->real_name_type === 'enterprise' ? '企业认证' : '个人认证' }}</b></div>
                        @if ($customer->real_name_type === 'enterprise')
                            <div><span>公司名称</span><b>{{ $customer->real_name_company ?: '—' }}</b></div>
                            <div><span>信用代码</span><b class="mono">{{ $customer->real_name_credit_code ?: '—' }}</b></div>
                            <div><span>法人</span><b>{{ $customer->real_name_legal_name ?: '—' }} · {{ $customer->real_name_legal_phone ?: '—' }}</b></div>
                        @else
                            <div><span>姓名</span><b>{{ $customer->real_name ?: '—' }}</b></div>
                            <div><span>身份证号</span><b class="mono">{{ $customer->real_name_id ? $customer->maskedRealNameId() : '—' }}</b></div>
                            <div><span>手机号</span><b class="mono">{{ $customer->real_name_phone ?: '—' }}</b></div>
                        @endif
                        <div><span>提交时间</span><b>{{ $customer->real_name_submitted_at?->format('Y-m-d H:i') ?? '—' }}</b></div>
                        <div><span>认证时间</span><b>{{ $customer->real_name_verified_at?->format('Y-m-d H:i') ?? '—' }}</b></div>
                    </div>
                    @if ($customer->real_name_status === 'rejected')
                        <p class="small muted" style="margin:12px 0 0">驳回原因：{{ $customer->real_name_reject_reason ?: '资料审核未通过' }}</p>
                    @endif
                    @if ($customer->real_name_type === 'enterprise' && is_array($customer->real_name_license))
                        <div class="small" style="margin-top:12px;display:flex;gap:14px"><a href="{{ route('admin.customers.real-name.file', [$customer, 'license']) }}" target="_blank">查看营业执照</a>@if (is_array($customer->real_name_legal_id))<a href="{{ route('admin.customers.real-name.file', [$customer, 'legal_id']) }}" target="_blank">查看法人身份证</a>@endif</div>
                    @endif
                    <div style="margin-top:16px"><a class="btn btn-ghost btn-sm" href="{{ route('admin.real-names.edit', $customer) }}">编辑实名资料</a></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>余额调整</h2><span class="meta">会写入复式流水</span></div>
                <div class="panel-body">
                    @if (auth('admin')->user()->canManageFinance())
                        <form method="POST" action="{{ route('admin.customers.balance', $customer) }}" class="filter-bar" style="align-items:flex-end">
                            @csrf
                            <div class="form-field"><label class="form-label">金额（正数充值 / 负数扣减）</label><input class="input" type="number" step="0.01" name="amount" placeholder="100.00" required></div>
                            <div class="form-field" style="flex:1"><label class="form-label">原因</label><input class="input" name="description" placeholder="活动赠送 / 退款冲正" required></div>
                            <button class="btn btn-soft" type="submit">确认调账</button>
                        </form>
                        <div class="note warn mt-3">调账会影响可用余额，请确认金额与原因。该操作会记录管理员、IP 与时间。</div>
                    @else
                        <div class="note">当前角色只能查看客户余额，不能执行调账。</div>
                    @endif
                </div>
            </div>
        </div>

        <div style="display:grid;gap:16px">
            <section class="panel customer-resources" aria-labelledby="customer-resources-title">
                <div class="panel-head customer-resource-heading"><span class="customer-resource-icon"><x-icon name="server" :size="21" /></span><div><h2 id="customer-resources-title">当前服务器</h2><p>配置、连接与到期信息，一目了然</p></div><span class="meta">{{ $customer->services_count }} 项资源</span></div>
                <div class="panel-body tight">
                    <form method="GET" action="{{ route('admin.customers.show', $customer) }}" class="customer-resource-filters">
                        <input class="input" type="search" name="service_q" value="{{ $serviceSearch }}" aria-label="搜索客户资源" placeholder="服务名称 / 编号 / IP">
                        <select class="select" name="service_status" aria-label="资源状态">
                            <option value="">全部状态</option>
                            <option value="pending" @selected($serviceStatus === 'pending')>待开通</option>
                            <option value="provisioning" @selected($serviceStatus === 'provisioning')>开通中</option>
                            <option value="active" @selected($serviceStatus === 'active')>运行中</option>
                            <option value="suspended" @selected($serviceStatus === 'suspended')>已暂停</option>
                            <option value="terminated" @selected($serviceStatus === 'terminated')>已终止</option>
                        </select>
                        <button class="btn btn-ghost btn-sm" type="submit">查询</button>
                        @if ($serviceSearch !== '' || $serviceStatus !== '')
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.customers.show', $customer) }}">重置</a>
                        @endif
                    </form>
                    <div class="customer-resource-list">
                        @forelse ($services as $service)
                            @php
                                $resource = \App\Support\ServicePresentation::service($service);
                                $cycle = $service->billing_cycle ?: 'monthly';
                                $renewal = data_get($service->specs, 'legacy_import.renewal_prices.'.$cycle)
                                    ?? data_get($service->specs, 'contract_prices.'.$cycle);
                                $renewalCycle = $cycle;
                                if (! is_numeric($renewal)) {
                                    $renewal = data_get($service->specs, 'renewal_amount');
                                    $renewalCycle = 'monthly';
                                }
                                $resourceIcon = match ($resource['kind']) { 'cdn' => 'globe', 'bare_metal' => 'hardware-chip', 'colocation' => 'building', 'game_server' => 'terminal', default => 'cloud' };
                            @endphp
                            <article class="customer-resource-card" data-resource-card>
                                <header><span class="customer-resource-kind"><x-icon :name="$resourceIcon" :size="18" />{{ $resource['family'] }}</span><x-status :status="$service->status" /></header>
                                <h3>{{ $resource['title'] }}</h3>@if (data_get($service->specs, 'admin_deletion'))<p class="service-deletion-badge">销毁结果待核对 · 服务记录已保留</p>@endif
                                <p class="customer-resource-reference">{{ $service->service_no }}<span> · </span>{{ $service->product?->name }}</p>
                                <p class="customer-resource-specs">{{ $resource['summary'] }}</p>
                                <dl class="customer-resource-facts">
                                    <div><dt>{{ $resource['network_label'] }}</dt><dd><span>{{ $resource['network'] }}</span>@if (! $resource['is_cdn'] && ! in_array($resource['network'], ['待同步', '未登记', '公网映射待就绪'], true))<button type="button" class="customer-resource-copy" data-copy="{{ $resource['network'] }}" aria-label="复制连接地址">复制</button>@endif</dd></div>
                                    <div><dt>{{ $resource['capacity_label'] }}</dt><dd>{{ $resource['capacity'] }}</dd></div>
                                    <div><dt>到期时间</dt><dd>{{ $service->expires_at?->format('Y-m-d') ?? '未设置' }}@if ($service->expires_at)<small>{{ $service->expires_at->format('H:i') }}</small>@endif</dd></div>
                                    <div><dt>续费金额</dt><dd>{{ $resource['kind'] === 'colocation' ? '人工确认报价' : (is_numeric($renewal) ? \App\Support\Money::format($renewal).' / '.\App\Support\Billing::label($renewalCycle) : '按商品配置') }}</dd></div>
                                </dl>
                                <footer><span class="customer-resource-region"><x-icon name="globe" :size="14" />{{ $resource['location'] }}</span><div><a class="btn btn-soft btn-sm" href="{{ route('admin.services.show', $service) }}">查看详情 <span aria-hidden="true">→</span></a>
                                    @if (auth('admin')->user()->canOperateServices() && $resource['kind'] !== 'colocation')
                                        <button class="btn btn-ghost btn-sm" type="button" data-service-edit data-name="{{ $service->name }}" data-expiry="{{ $service->expires_at?->format('Y-m-d\TH:i') }}" data-renewal="{{ $serviceRenewalQuotes[$service->id] ?? '' }}" data-service-no="{{ $service->service_no }}" data-source="{{ $service->product?->upstream_source ?: 'local' }}" data-provider="{{ ['mofang' => '商品供应商', 'qzvps' => '轻舟主控', 'pterodactyl' => '翼龙面板', 'local' => 'demovps 主控'][$service->product?->upstream_source ?: 'local'] ?? '人工登记' }}" data-external-ref="{{ $service->external_ref }}" data-cycle="{{ \App\Support\Billing::label($service->billing_cycle ?: 'monthly') }}" data-auto-renew="{{ $service->auto_renew ? '1' : '0' }}" data-deletion-pending="{{ data_get($service->specs, 'admin_deletion') ? '1' : '0' }}" data-action="{{ route('admin.customers.services.update', [$customer, $service]) }}" data-delete-action="{{ route('admin.customers.services.destroy', [$customer, $service]) }}">调整</button>
                                    @endif
                                </div></footer>
                            </article>
                        @empty
                            <div class="customer-resource-empty"><x-icon name="search" :size="28" /><b>没有匹配的资源</b><span>试试其他名称、编号、IP 或资源状态。</span></div>
                        @endforelse
                    </div>
                    @if ($services->hasPages())<div class="panel-footer">{{ $services->links('partials.pagination') }}</div>@endif
                </div>
            </section>

            <div class="modal service-adjust-modal" id="service-edit-modal" role="dialog" aria-modal="true" aria-labelledby="service-adjust-title" aria-hidden="true">
                <div class="modal-card">
                    <div class="modal-head"><div class="service-adjust-heading"><span><x-icon name="settings" :size="22" /></span><div><h2 id="service-adjust-title">调整服务</h2><p>维护服务资料、账务到期与续费设置</p></div></div><button class="modal-close" type="button" id="close-service-edit" aria-label="关闭调整服务">×</button></div>
                    <div class="modal-body">
                        <div class="service-adjust-summary"><div><small>当前服务</small><b id="service-edit-name"></b><span data-adjust-service-no></span></div><div><small>资源平台</small><b data-adjust-provider></b><span data-adjust-binding></span></div></div>
                        <p class="service-adjust-pending" data-adjust-pending hidden>该服务已提交销毁申请，调整暂时锁定。请在下方核对删除结果。</p>
                        <form method="POST" id="service-edit-form">
                            @csrf @method('PUT')
                            <fieldset data-adjust-fields>
                                <div class="service-adjust-section"><h3><x-icon name="file" :size="17" />服务资料</h3><div class="form-field"><label class="form-label" for="service-edit-display-name">服务显示名称</label><input class="input" name="name" id="service-edit-display-name" maxlength="190" required><p class="service-adjust-help">用于本站展示，不修改虚拟机主机名和资源绑定。</p></div></div>
                                <div class="service-adjust-section"><h3><x-icon name="clock" :size="17" />账务与续费</h3><div class="service-adjust-grid">
                                    <div class="form-field"><label class="form-label" for="service-edit-expiry">到期时间</label><input class="input" type="datetime-local" name="expires_at" id="service-edit-expiry"><div class="service-expiry-shortcuts" data-expiry-shortcuts><button type="button" data-expiry-days="1">＋1 天</button><button type="button" data-expiry-days="7">＋7 天</button><button type="button" data-expiry-days="30">＋30 天</button></div><p class="service-adjust-help" data-expiry-help>调整本站账务到期时间，不产生付款订单，也不会自动延长资源平台租期。</p></div>
                                    <div class="form-field"><label class="form-label" for="service-edit-renewal-mode">续费价格</label><select class="select" name="renewal_mode" id="service-edit-renewal-mode"><option value="keep">保留当前定价</option><option value="custom">设置当前周期专属价</option></select><div class="service-adjust-price"><span>¥</span><input class="input" type="number" step="0.01" min="0" max="9999999" name="renewal_amount" id="service-edit-renewal" placeholder="按商品配置" disabled><b data-adjust-cycle></b></div><p class="service-adjust-help">保留当前定价不会覆盖历史价格；专属价仅影响此服务今后的当前周期续费，不修改已有账单。</p></div>
                                </div><label class="service-adjust-switch"><div><b>自动续费</b><small>按本站自动续费规则执行，受账户余额与服务状态限制。</small></div><input type="hidden" name="auto_renew" value="0"><input type="checkbox" name="auto_renew" value="1" id="service-edit-auto-renew"></label></div>
                                <div class="service-adjust-section"><label class="form-label" for="service-edit-reason">调整原因</label><textarea class="textarea" name="reason" id="service-edit-reason" rows="2" minlength="2" maxlength="500" placeholder="例如：按客户合同调整续费价格" required></textarea><p class="service-adjust-help">仅记录在后台操作日志，便于后续核查。</p></div>
                                <div class="service-adjust-save"><span><x-icon name="shield" :size="15" />修改操作会留存记录</span><button class="btn btn-ghost" type="button" id="cancel-service-edit">取消</button><button class="btn btn-primary" type="submit">保存调整</button></div>
                            </fieldset>
                        </form>
                        <div class="service-adjust-danger"><span class="service-adjust-danger-icon"><x-icon name="alert" :size="21" /></span><div><b>销毁并删除服务</b><p>同步处理对应平台资源，确认终止后再移除本站服务。历史账务保留。</p></div><button class="btn btn-danger btn-sm" type="button" data-open-service-delete>删除服务</button></div>
                        <p class="service-adjust-live" role="status" data-adjust-live></p>
                    </div>
                </div>
            </div>
            <dialog class="service-destroy-dialog" id="service-destroy-dialog" aria-labelledby="service-destroy-title">
                <div class="service-destroy-heading"><span><x-icon name="alert" :size="25" /></span><div><h2 id="service-destroy-title">确认销毁这项服务？</h2><p>这会影响对应平台的真实资源，请核对目标。</p></div><button type="button" class="modal-close" data-cancel-service-delete aria-label="关闭删除确认">×</button></div>
                <div class="service-destroy-target"><b data-destroy-name></b><dl><div><dt>服务编号</dt><dd data-destroy-number></dd></div><div><dt>资源平台</dt><dd data-destroy-provider></dd></div><div><dt>资源绑定</dt><dd data-destroy-binding></dd></div></dl></div>
                <div class="service-destroy-warning"><b>资源数据可能无法恢复</b><p>本站不会仅隐藏服务。接口失败或销毁结果未确认时，会保留服务及绑定信息，等待核对。</p></div>
                <form method="POST" id="service-delete-form">
                    @csrf @method('DELETE')
                    <label class="form-label" for="service-delete-number">输入服务编号确认 <strong data-destroy-number></strong></label><input class="input" id="service-delete-number" name="confirm_service_no" autocomplete="off" required>
                    <label class="form-label" for="service-delete-reason">删除原因</label><textarea class="textarea" id="service-delete-reason" name="reason" rows="2" minlength="2" maxlength="500" placeholder="填写删除原因，用于后台操作留痕" required></textarea>
                    <label class="service-delete-ack"><input type="checkbox" name="acknowledge" value="1" required><span>我已核对服务及资源绑定，了解同步销毁的影响。</span></label>
                    <div class="service-destroy-actions"><button class="btn btn-ghost" type="button" data-cancel-service-delete>返回调整</button><button class="btn btn-danger" type="submit" data-submit-service-delete disabled>同步销毁并删除</button></div>
                    <p class="service-adjust-live" role="status" data-delete-live></p>
                </form>
            </dialog>

            <div class="panel">
                <div class="panel-head"><h2>最近订单</h2><span class="meta">{{ $customer->orders_count }} 笔</span></div>
                <div class="panel-body tight">
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>订单号</th><th>金额</th><th>状态</th><th>时间</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($customer->orders as $order)
                                    <tr>
                                        <td class="mono small">{{ $order->order_no }}</td>
                                        <td class="strong small">{{ \App\Support\Money::format($order->total) }}</td>
                                        <td><x-status :status="$order->status" /></td>
                                        <td class="small muted">{{ $order->created_at?->format('m-d H:i') }}</td>
                                        <td><a class="btn btn-ghost btn-sm" href="{{ route('admin.orders.show', $order) }}">查看</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="table-empty">该客户暂无订单。</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($customer->orders_count > $customer->orders->count())
                        <div class="panel-footer small muted">仅显示最近 {{ $customer->orders->count() }} 笔，共 {{ $customer->orders_count }} 笔。</div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>最近工单</h2><span class="meta">{{ $customer->tickets_count }} 个</span></div>
                <div class="panel-body tight">
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>工单</th><th>状态</th><th>时间</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($customer->tickets as $ticket)
                                    <tr>
                                        <td><div class="strong small">{{ $ticket->subject }}</div><div class="small muted">{{ $ticket->ticket_no }}</div></td>
                                        <td><x-status :status="$ticket->status" /></td>
                                        <td class="small muted">{{ $ticket->created_at?->format('m-d H:i') }}</td>
                                        <td><a class="btn btn-ghost btn-sm" href="{{ route('admin.tickets.show', $ticket) }}">查看</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="table-empty">该客户暂无工单。</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($customer->tickets_count > $customer->tickets->count())
                        <div class="panel-footer small muted">仅显示最近 {{ $customer->tickets->count() }} 个，共 {{ $customer->tickets_count }} 个。</div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>余额流水</h2></div>
                <div class="panel-body">
                    @forelse ($customer->ledgerEntries as $entry)
                        <div class="flex justify-between gap-3" style="padding:7px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)">
                            <div><div class="small">{{ $entry->description }}</div><div class="small muted">{{ $entry->created_at?->format('m-d H:i') }}</div></div>
                            <div style="text-align:right"><b class="small">{{ \App\Support\Money::format($entry->amount) }}</b><div class="small muted">余 {{ \App\Support\Money::format($entry->balance_after) }}</div></div>
                        </div>
                    @empty
                        <div class="small muted">暂无流水。</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <dialog id="delete-customer-dialog" class="customer-delete-dialog">
        <div class="customer-delete-body">
            <h3>删除客户</h3>
            <p data-delete-message>确认删除该客户？删除后将无法登录。</p>
            <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" id="delete-customer-form">
                @csrf
                @method('DELETE')
                <div class="customer-delete-actions">
                    <button class="btn btn-ghost" type="button" data-delete-cancel>取消</button>
                    <button class="btn btn-danger" type="submit" data-delete-submit>确认删除</button>
                </div>
            </form>
        </div>
    </dialog>

    <script src="{{ asset('assets/js/customer-service.js') }}?v={{ filemtime(public_path('assets/js/customer-service.js')) }}" defer></script>
    <script src="{{ asset('assets/js/customer-delete.js') }}" defer></script>
@endsection
