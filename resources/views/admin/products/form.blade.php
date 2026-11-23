@extends('layouts.admin')

@section('title', $product->exists ? '编辑商品' : '新建商品')
@section('page-title', $product->exists ? '编辑商品' : '新建商品')

@section('content')
    @php
        $metadata = $product->metadata ?? [];
        $controller = $metadata['controller'] ?? [];
        $specs = $metadata['specs'] ?? [];
        $detailRows = old('details', is_array($metadata['details'] ?? null) ? $metadata['details'] : []);
        if ($detailRows === []) {
            $detailRows = [['label' => '', 'value' => '']];
        }
        $initialTab = 'details';
        if ($errors->hasAny(['prices', 'prices.*', 'cycle_discounts', 'cycle_discounts.*'])) {
            $initialTab = 'options';
        } elseif ($errors->hasAny(['controller_node_id', 'controller_image_id', 'controller_ip_pool_id', 'controller_ostype', 'controller_network_mode', 'default_cores', 'default_memory_gb', 'default_disk_gb', 'default_data_disk_gb', 'default_upload_mbps', 'default_download_mbps'])) {
            $initialTab = 'config';
        } elseif ($errors->hasAny(['options', 'options.*', 'options.*.*'])) {
            $initialTab = 'options';
        }
    @endphp

    <div class="page-head">
        <div><h1>{{ $product->exists ? '编辑商品' : '新建商品' }}</h1><p>商品信息、定价、主控配置和可配置选项统一在这里维护。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">返回列表</a>
    </div>

    @if ($controllerError)
        <div class="note danger" style="margin-bottom:14px">{{ $controllerError }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    @if ($product->upstream_source === 'pterodactyl')
        @php
            $ptero = is_array($metadata['pterodactyl'] ?? null) ? $metadata['pterodactyl'] : [];
            $pteroLimits = is_array($ptero['limits'] ?? null) ? $ptero['limits'] : [];
            $pteroBaseUrl = (string) data_get(\App\Services\Pterodactyl\PterodactylSettings::configuration(), 'base_url', 'https://pl.deviy.cn');
        @endphp
        <div class="panel" style="max-width:1180px;margin-bottom:16px;border-color:var(--dt-e-bfdbfe,#bfdbfe)">
            <div class="panel-head"><h2>翼龙面板绑定信息</h2><span class="meta">商品来源：翼龙面板</span></div>
            <div class="panel-body">
                <div class="instance-info-grid">
                    <div class="instance-field"><span>面板地址</span><b class="mono">{{ $pteroBaseUrl }}</b></div>
                    <div class="instance-field"><span>Nest 编号</span><b class="mono">#{{ $ptero['nest_id'] ?? '—' }}</b></div>
                    <div class="instance-field"><span>Egg 编号</span><b class="mono">#{{ $ptero['egg_id'] ?? '—' }}</b></div>
                    <div class="instance-field"><span>Node 编号</span><b class="mono">#{{ $ptero['node_id'] ?? '—' }}</b></div>
                    <div class="instance-field"><span>Docker 镜像</span><b class="mono" style="word-break:break-all">{{ $ptero['docker_image'] ?? '—' }}</b></div>
                    <div class="instance-field"><span>启动命令</span><b class="mono" style="word-break:break-all">{{ $ptero['startup'] ?? '—' }}</b></div>
                    <div class="instance-field"><span>资源限制</span><b class="mono">{{ $pteroLimits['memory'] ?? '—' }} MB 内存 / {{ $pteroLimits['disk'] ?? '—' }} MB 磁盘 / {{ $pteroLimits['cpu'] ?? '—' }}% CPU</b></div>
                    <div class="instance-field"><span>环境变量</span><b class="mono">{{ count((array) ($ptero['environment'] ?? [])) }} 个</b></div>
                </div>
                <div class="note info" style="margin-top:14px">翼龙绑定信息为只读。如需更换 Nest / Egg / Node，请到“翼龙面板商品对接”重新导入商品。</div>
                <a class="btn btn-ghost btn-sm" href="{{ route('admin.pterodactyl.index') }}" style="margin-top:10px">翼龙面板商品管理</a>
            </div>
        </div>
    @endif

    <div class="panel" style="max-width:1180px">
        <div class="panel-body">
            <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
                @csrf
                @if ($product->exists) @method('PUT') @endif

                <div class="product-tabs" role="tablist">
                    <button type="button" class="product-tab {{ $initialTab === 'details' ? 'active' : '' }}" data-product-tab="details">详情</button>
                    <button type="button" class="product-tab {{ $initialTab === 'config' ? 'active' : '' }}" data-product-tab="config">产品配置</button>
                    <button type="button" class="product-tab {{ $initialTab === 'options' ? 'active' : '' }}" data-product-tab="options">配置选项</button>
                </div>

                <div class="product-tab-pane {{ $initialTab === 'details' ? 'active' : '' }}" data-product-pane="details">
                    <div class="form-grid">
                        <div class="form-field"><label class="form-label">商品名称 <span>*</span></label><input class="input" name="name" value="{{ old('name', $product->name) }}" required></div>
                        <div class="form-field"><label class="form-label">商品编码 <span>*</span></label><input class="input" name="code" value="{{ old('code', $product->code) }}" placeholder="HK-CN2-2C4G" required></div>
                        <div class="form-field"><label class="form-label">商品分组 <span>*</span></label><select class="select" name="product_group_id">@foreach ($groups as $group)<option value="{{ $group->id }}" @selected(old('product_group_id', $product->product_group_id) == $group->id)>{{ $group->name }}</option>@foreach ($group->children as $child)<option value="{{ $child->id }}" @selected(old('product_group_id', $product->product_group_id) == $child->id)>{{ $group->name }} / {{ $child->name }}</option>@endforeach @endforeach</select></div>
                        <div class="form-field"><label class="form-label">商品类型 <span>*</span></label><select class="select" name="type">@foreach (['cloud_vm' => '云服务器', 'nat_vm' => 'NAT 云主机', 'game_server' => '翼龙游戏服务器', 'bare_metal' => '裸金属服务器', 'cdn' => 'CDN 加速', 'colocation' => '服务器托管', 'dedicated_ip' => '独立 IP', 'bandwidth' => '带宽', 'other' => '其他'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $product->type ?: 'cloud_vm') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">上游来源</label><select class="select" name="upstream_source">@foreach (['manual' => '人工托管 / 无接口', 'local' => '本机 Controller', 'mofang' => '魔方上游', 'pterodactyl' => '翼龙面板', 'qzvps' => '轻舟 VPS'] as $value => $label)<option value="{{ $value }}" @selected(old('upstream_source', $product->upstream_source ?: 'local') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">地域 <span>*</span></label><input class="input" name="region" value="{{ old('region', $product->region ?: '香港') }}" required></div>
                        <div class="form-field"><label class="form-label">库存</label><input class="input" type="number" name="stock" value="{{ old('stock', $product->stock ?? 999) }}" min="0" required></div>
                        <div class="form-field"><label class="form-label">状态</label><select class="select" name="status">@foreach (['draft' => '草稿', 'active' => '上架', 'inactive' => '下架'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $product->status ?: 'draft') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">排序</label><input class="input" type="number" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" min="0"></div>
                        <div class="form-field full"><label class="form-label">一句话描述</label><input class="input" name="short_description" value="{{ old('short_description', $product->short_description) }}" placeholder="2 核 4G · 40G SSD · 10Mbps · 独立 IPv4"></div>
                        <div class="form-field full"><label class="form-label">详细描述</label><textarea class="textarea" name="description" rows="3">{{ old('description', $product->description) }}</textarea></div>
                        <div class="form-field full">
                            <label class="form-label">商品详情页内容</label>
                            <textarea class="textarea" name="detail_page_content" rows="6" placeholder="显示在单独的商品详情页，支持换行">{{ old('detail_page_content', $metadata['detail_page_content'] ?? '') }}</textarea>
                            <div class="meta">购买页只保留配置和价格；这里的内容会显示在「商品详情」单独页面。</div>
                        </div>
                        <div class="form-field full">
                            <label class="form-label">卡片介绍文</label>
                            <input class="input" name="card_intro" value="{{ old('card_intro', $metadata['card_intro'] ?? '') }}" placeholder="显示在商品卡片规格区上方的介绍文字">
                            <div class="meta">显示在商品列表卡片标题下方、规格参数上方；留空则不显示。</div>
                        </div>
                        <div class="form-field full">
                            <label class="form-label">商品参数（商品卡片规格区）</label>
                            <div class="param-editor" data-param-editor>
                                @foreach ($detailRows as $index => $row)
                                    <div class="param-row">
                                        <input class="input" name="details[{{ $index }}][label]" value="{{ is_array($row) ? ($row['label'] ?? '') : '' }}" placeholder="参数名称，例如 CPU 核心">
                                        <input class="input" name="details[{{ $index }}][value]" value="{{ is_array($row) ? ($row['value'] ?? '') : '' }}" placeholder="参数值，例如 4 vCPU">
                                        <button class="btn btn-ghost btn-sm" type="button" data-remove-param>删除</button>
                                    </div>
                                @endforeach
                            </div>
                            <template id="param-row-template">
                                <div class="param-row">
                                    <input class="input" name="details[__INDEX__][label]" placeholder="参数名称，例如 CPU 核心">
                                    <input class="input" name="details[__INDEX__][value]" placeholder="参数值，例如 4 vCPU">
                                    <button class="btn btn-ghost btn-sm" type="button" data-remove-param>删除</button>
                                </div>
                            </template>
                            <div class="flex items-center gap-3" style="margin-top:8px">
                                <button class="btn btn-ghost btn-sm" type="button" data-add-param>+ 添加参数</button>
                                <span class="meta">对应商品卡片标题下方的 CPU、内存、带宽等参数区，可自由增减和排序。</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="product-tab-pane {{ $initialTab === 'config' ? 'active' : '' }}" data-product-pane="config">
                    <div class="panel-head" style="padding:0;border:0;margin:0 0 6px"><h2>主控与服务器规格</h2><span class="meta">支付成功后按此配置调用 Controller 创建</span></div>
                    @if (old('upstream_source', $product->upstream_source) === 'qzvps' || ! $product->exists)
                        @php($qzvps = is_array($metadata['qzvps'] ?? null) ? $metadata['qzvps'] : [])
                        <div class="note info" style="margin-bottom:14px">轻舟 VPS 商品：CPU、内存、磁盘和上下行带宽取「配置选项」首个值；请确保选项值已填写。</div>
                        <div class="form-grid">
                            <div class="form-field"><label class="form-label">线路</label>
                                <select class="select" name="qzvps_line_id">
                                    <option value="">请选择线路</option>
                                    @foreach ($qzvpsLines as $line)
                                        <option value="{{ $line['id'] ?? '' }}" @selected((string) old('qzvps_line_id', $qzvps['line_id'] ?? '') === (string) ($line['id'] ?? ''))>{{ $line['line_name'] ?? ('线路 '.($line['id'] ?? '')) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-field"><label class="form-label">宿主机节点</label>
                                <select class="select" name="qzvps_node_id">
                                    <option value="">请选择节点</option>
                                    @foreach ($qzvpsNodes as $node)
                                        <option value="{{ $node['id'] ?? '' }}" @selected((string) old('qzvps_node_id', $qzvps['node_id'] ?? '') === (string) ($node['id'] ?? ''))>{{ $node['name'] ?? ('节点 '.($node['id'] ?? '')) }} · {{ $node['line_name'] ?? '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-field"><label class="form-label">CPU 核数</label><input class="input" type="number" name="qzvps_cpu" value="{{ old('qzvps_cpu', data_get($metadata, 'specs.cpu', 2)) }}" min="1"></div>
                            <div class="form-field"><label class="form-label">内存 GB</label><input class="input" type="number" name="qzvps_memory_gb" value="{{ old('qzvps_memory_gb', data_get($metadata, 'specs.memory_gb', 4)) }}" min="1"></div>
                            <div class="form-field"><label class="form-label">硬盘 GB</label><input class="input" type="number" name="qzvps_disk_gb" value="{{ old('qzvps_disk_gb', data_get($metadata, 'specs.disk_gb', 40)) }}" min="10"></div>
                            <div class="form-field"><label class="form-label">上行 Mbps</label><input class="input" type="number" name="qzvps_upload_mbps" value="{{ old('qzvps_upload_mbps', data_get($metadata, 'specs.upload_mbps', 10)) }}" min="0"></div>
                            <div class="form-field"><label class="form-label">下行 Mbps</label><input class="input" type="number" name="qzvps_download_mbps" value="{{ old('qzvps_download_mbps', data_get($metadata, 'specs.download_mbps', 100)) }}" min="0"></div>
                            <div class="form-field"><label class="form-label">系统模板</label>
                                <select class="select" name="qzvps_os_name">
                                    <option value="">请选择系统</option>
                                    @foreach ($qzvpsImages as $image)
                                        <option value="{{ $image['name'] ?? '' }}" @selected(old('qzvps_os_name', $qzvps['os_name'] ?? 'Ubuntu22.04') === ($image['name'] ?? ''))>{{ $image['name'] ?? '' }} · {{ $image['type'] ?? '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-field"><label class="form-label">IP 类型</label><select class="select" name="qzvps_ipnum"><option value="0" @selected((int) old('qzvps_ipnum', $qzvps['ipnum'] ?? 0) === 0)>NAT 共享 IP</option><option value="1" @selected((int) old('qzvps_ipnum', $qzvps['ipnum'] ?? 0) === 1)>独立 IP</option></select></div>
                            <div class="form-field"><label class="form-label">NAT 端口数</label><input class="input" type="number" name="qzvps_port_num" value="{{ old('qzvps_port_num', $qzvps['port_num'] ?? 10) }}" min="0"></div>
                            <div class="form-field"><label class="form-label">嵌套虚拟化</label><select class="select" name="qzvps_metal"><option value="0" @selected((int) old('qzvps_metal', $qzvps['metal'] ?? 0) === 0)>关闭</option><option value="1" @selected((int) old('qzvps_metal', $qzvps['metal'] ?? 0) === 1)>开启</option></select></div>
                            <div class="form-field"><label class="form-label">显卡</label><select class="select" name="qzvps_open_gpu"><option value="0" @selected((int) old('qzvps_open_gpu', $qzvps['open_gpu'] ?? 0) === 0)>关闭</option><option value="1" @selected((int) old('qzvps_open_gpu', $qzvps['open_gpu'] ?? 0) === 1)>开启</option></select></div>
                            <div class="form-field"><label class="form-label">显卡序号</label><input class="input" name="qzvps_mdevid" value="{{ old('qzvps_mdevid', $qzvps['mdevid'] ?? '1') }}"></div>
                        </div>
                    @endif
                    <div class="note info" style="margin-bottom:14px">上架云服务器或 NAT 云机必须选择宿主机主控节点；否则只能保存为草稿或下架。</div>
                    <div class="form-grid">
                        <div class="form-field"><label class="form-label">宿主机主控节点 <span>*</span></label>
                            <select class="select" name="controller_node_id">
                                <option value="">请选择宿主机主控节点</option>
                                @foreach ($controllerNodes as $node)
                                    <option value="{{ $node['id'] ?? '' }}" @selected(old('controller_node_id', $controller['node_id'] ?? '') === ($node['id'] ?? ''))>{{ $node['name'] ?? $node['id'] }} · {{ strtoupper($node['provider'] ?? '') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field"><label class="form-label">客户连接域名 / 公网入口</label><input class="input" name="controller_public_host" value="{{ old('controller_public_host',$controller['public_host']??'') }}" placeholder="例如 p7.deviy.cn"><small class="muted">仅用于连接信息展示；主控节点的公网 IP 必须填写数值 IP。</small></div>
                        <div class="form-field"><label class="form-label">系统镜像</label>
                            <select class="select" name="controller_image_id">
                                <option value="">不使用镜像</option>
                                @foreach ($controllerImages as $image)
                                    <option value="{{ $image['id'] ?? '' }}" @selected(old('controller_image_id', $controller['image_id'] ?? '') === ($image['id'] ?? ''))>{{ $image['name'] ?? '' }} · {{ $image['ostype'] ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field"><label class="form-label">IP 池</label>
                            <select class="select" name="controller_ip_pool_id">
                                <option value="">不分配固定 IP</option>
                                @foreach ($controllerPools as $pool)
                                    <option value="{{ $pool['id'] ?? '' }}" @selected(old('controller_ip_pool_id', $controller['ip_pool_id'] ?? '') === ($pool['id'] ?? ''))>{{ $pool['name'] ?? '' }} · {{ $pool['cidr'] ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field"><label class="form-label">网络交付方式</label><select class="select" name="controller_network_mode"><option value="private" @selected(old('controller_network_mode', $controller['network_mode'] ?? 'private') === 'private')>NAT 转发 / 共享 IP</option><option value="public" @selected(old('controller_network_mode', $controller['network_mode'] ?? '') === 'public')>独立 IP</option></select></div>
                        <div class="form-field"><label class="form-label">到期提醒（提前天数）</label><input class="input" type="number" name="expiry_reminder_days" value="{{ old('expiry_reminder_days', $metadata['expiry_reminder_days'] ?? 7) }}" min="0"></div>
                    </div>
                </div>

                <div class="product-tab-pane {{ $initialTab === 'options' ? 'active' : '' }}" data-product-pane="options">
                    <div class="panel-head" style="padding:0;border:0;margin:0 0 6px"><h2>可配置选项</h2><span class="meta">第一个选项为默认配置，价格为月付加价</span></div>
                    <div class="note info" style="margin-bottom:14px">客户在商品页选择不同配置后，订单按「基础月付价 + 所选配置月付加价」计算月付总价，再按周期月数和折扣换算。</div>

                    <div class="option-config-card" style="background:var(--dt-b-f8fbff,#f8fbff)">
                        <div class="option-config-head"><b>基础月付价格</b><span>所有配置共用</span></div>
                        <div class="form-grid">
                            <div class="form-field"><label class="form-label">月付基础价（元）</label><input class="input" type="number" step="0.01" min="0" name="prices[monthly]" value="{{ old('prices.monthly', $product->prices->firstWhere('billing_cycle', 'monthly')?->price) }}" placeholder="例如 28.00"></div>
                            @foreach (['quarterly' => '季付折扣', 'semiannually' => '半年付折扣', 'annually' => '年付折扣'] as $cycle => $discountLabel)
                                @php($discount = (float) old('cycle_discounts.'.$cycle, $metadata['cycle_discounts'][$cycle] ?? 1))
                                <div class="form-field"><label class="form-label">{{ $discountLabel }}</label>
                                    <select class="select" name="cycle_discounts[{{ $cycle }}]" data-cycle-discount="{{ $cycle }}">
                                        @foreach ([['value' => '1', 'label' => '无折扣'], ['value' => '0.95', 'label' => '95 折'], ['value' => '0.9', 'label' => '9 折'], ['value' => '0.85', 'label' => '85 折'], ['value' => '0.8', 'label' => '8 折']] as $option)
                                            <option value="{{ $option['value'] }}" @selected(abs($discount - (float) $option['value']) < 0.001)>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @foreach ($optionDefinitions as $key => $definition)
                        @php($rows = old('options.'.$key, $optionRows[$key] ?? []))
                        @php($setting = old('option_settings.'.$key, $optionSettings[$key] ?? []))
                        <div class="option-config-card">
                            <div class="option-config-head"><b>{{ $definition['label'] }}</b><span>月付加价</span></div>
                            <div class="option-settings">
                                <div class="form-field" style="min-width:220px;flex:1"><label class="form-label">说明文字</label><input class="input" name="option_settings[{{ $key }}][hint]" value="{{ $setting['hint'] ?? '选择配置，价格实时计算' }}" placeholder="选择配置，价格实时计算"></div>
                                @if (in_array($key, \App\Support\ProductOptions::RANGE_DIMENSIONS, true))
                                    <div class="form-field"><label class="form-label">选择方式</label><select class="select" name="option_settings[{{ $key }}][range_enabled]"><option value="0" @selected(empty($setting['range']['enabled']))>固定选项</option><option value="1" @selected(! empty($setting['range']['enabled']))>范围拖动</option></select></div>
                                    <div class="form-field"><label class="form-label">最小值</label><input class="input" type="number" min="0" step="0.01" name="option_settings[{{ $key }}][range_min]" value="{{ $setting['range']['min'] ?? 0 }}"></div>
                                    <div class="form-field"><label class="form-label">最大值</label><input class="input" type="number" min="0" step="0.01" name="option_settings[{{ $key }}][range_max]" value="{{ $setting['range']['max'] ?? 0 }}"></div>
                                    <div class="form-field"><label class="form-label">步长</label><input class="input" type="number" min="0.01" step="0.01" name="option_settings[{{ $key }}][range_step]" value="{{ $setting['range']['step'] ?? 1 }}"></div>
                                    <div class="form-field"><label class="form-label">单价（元/超出最小值单位/月）</label><input class="input" type="number" min="0" step="0.01" name="option_settings[{{ $key }}][range_unit_price]" value="{{ $setting['range']['unit_price'] ?? 0 }}"></div>
                                @endif
                            </div>
                            @if (in_array($key, \App\Support\ProductOptions::RANGE_DIMENSIONS, true))
                                <div class="meta" style="margin:-3px 0 9px">启用「范围拖动」后，以下固定选项不再显示，价格按「拖动值 × 单价」计算。</div>
                            @endif
                            <div class="option-rows" data-option-editor="{{ $key }}">
                                @if ($rows === [])
                                    <input type="hidden" name="options[{{ $key }}][__deleted]" value="1">
                                @endif
                                @foreach ($rows as $index => $row)
                                    @if ($key === 'ostype')
                                        @php($selectedOS = (string) ($row['value'] ?? 'l26'))
                                        <div class="option-row option-row-os">
                                            <select class="select" name="options[{{ $key }}][{{ $index }}][value]" data-os-select required>
                                                @foreach (\App\Support\ProductOptions::OS_OPTIONS as $osValue => $osLabel)
                                                    <option value="{{ $osValue }}" @selected($selectedOS === $osValue)>{{ $osLabel }}</option>
                                                @endforeach
                                                @if ($selectedOS !== '' && ! array_key_exists($selectedOS, \App\Support\ProductOptions::OS_OPTIONS))
                                                    <option value="{{ $selectedOS }}" selected>{{ $row['label'] ?? $selectedOS }}</option>
                                                @endif
                                            </select>
                                            <input type="hidden" name="options[{{ $key }}][{{ $index }}][label]" value="{{ $row['label'] ?? (\App\Support\ProductOptions::OS_OPTIONS[$selectedOS] ?? $selectedOS) }}" data-os-label>
                                            <input class="input" name="options[{{ $key }}][{{ $index }}][price]" value="{{ $row['price'] ?? 0 }}" type="number" min="0" step="0.01" placeholder="0.00">
                                            <button class="btn btn-ghost btn-sm" type="button" data-remove-option>删除</button>
                                        </div>
                                    @else
                                        <div class="option-row option-row-standard">
                                            <input class="input" name="options[{{ $key }}][{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" type="{{ $definition['type'] === 'int' ? 'number' : 'text' }}" placeholder="值" required>
                                            <input class="input" name="options[{{ $key }}][{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="显示名称">
                                            <input class="input" name="options[{{ $key }}][{{ $index }}][price]" value="{{ $row['price'] ?? 0 }}" type="number" min="0" step="0.01" placeholder="0.00">
                                            <button class="btn btn-ghost btn-sm" type="button" data-remove-option>删除</button>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <template id="option-template-{{ $key }}">
                                @if ($key === 'ostype')
                                    <div class="option-row option-row-os">
                                        <select class="select" name="options[{{ $key }}][__INDEX__][value]" data-os-select required>
                                            @foreach (\App\Support\ProductOptions::OS_OPTIONS as $osValue => $osLabel)
                                                <option value="{{ $osValue }}">{{ $osLabel }}</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="options[{{ $key }}][__INDEX__][label]" value="Windows Server 2022" data-os-label>
                                        <input class="input" name="options[{{ $key }}][__INDEX__][price]" value="0" type="number" min="0" step="0.01" placeholder="0.00">
                                        <button class="btn btn-ghost btn-sm" type="button" data-remove-option>删除</button>
                                    </div>
                                @else
                                    <div class="option-row option-row-standard">
                                        <input class="input" name="options[{{ $key }}][__INDEX__][value]" type="{{ $definition['type'] === 'int' ? 'number' : 'text' }}" placeholder="值" required>
                                        <input class="input" name="options[{{ $key }}][__INDEX__][label]" placeholder="显示名称">
                                        <input class="input" name="options[{{ $key }}][__INDEX__][price]" value="0" type="number" min="0" step="0.01" placeholder="0.00">
                                        <button class="btn btn-ghost btn-sm" type="button" data-remove-option>删除</button>
                                    </div>
                                @endif
                            </template>
                            <button class="btn btn-ghost btn-sm" type="button" data-add-option data-key="{{ $key }}">+ 添加{{ $definition['label'] }}选项</button>
                        </div>
                    @endforeach
                </div>

                <div class="product-form-actions">
                    <button class="btn btn-primary" type="submit">保存商品</button>
                    <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">返回</a>
                    <button class="btn btn-ghost" type="reset">取消更改</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        document.querySelectorAll('[data-product-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('[data-product-tab]').forEach(function (item) {
                    item.classList.toggle('active', item === button);
                });
                document.querySelectorAll('[data-product-pane]').forEach(function (pane) {
                    pane.classList.toggle('active', pane.dataset.productPane === button.dataset.productTab);
                });
            });
        });

        document.querySelectorAll('[data-add-param]').forEach(function (button) {
            button.addEventListener('click', function () {
                const editor = button.closest('.form-field')?.querySelector('[data-param-editor]');
                const template = document.getElementById('param-row-template');
                if (!editor || !template) {
                    return;
                }
                const index = editor.querySelectorAll('.param-row').length;
                editor.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
            });
        });

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-param]');
            if (!button) {
                return;
            }
            const editor = button.closest('[data-param-editor]');
            if (editor && editor.querySelectorAll('.param-row').length <= 1) {
                return;
            }
            button.closest('.param-row')?.remove();
        });

        document.querySelectorAll('[data-add-option]').forEach(function (button) {            button.addEventListener('click', function () {
                const key = button.dataset.key;
                const editor = document.querySelector('[data-option-editor="' + key + '"]');
                const template = document.getElementById('option-template-' + key);
                editor.querySelector('input[name="options[' + key + '][__deleted]"]')?.remove();
                const index = editor.querySelectorAll('.option-row').length;
                editor.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
                editor.querySelectorAll('[data-os-select]').forEach(syncOsLabel);
            });
        });

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-option]');
            if (!button) {
                return;
            }
            const editor = button.closest('.option-rows');
            const key = editor?.dataset.optionEditor;
            button.closest('.option-row')?.remove();
            if (editor && key && editor.querySelectorAll('.option-row').length === 0) {
                editor.insertAdjacentHTML('beforeend', '<input type="hidden" name="options[' + key + '][__deleted]" value="1">');
            }
        });

        document.querySelectorAll('[data-cycle-discount]').forEach(function (select) {
            select.addEventListener('change', function () {
                const cycle = select.dataset.cycleDiscount;
                const months = { quarterly: 3, semiannually: 6, annually: 12 };
                const monthly = document.querySelector('input[name="prices[monthly]"]');
                const target = document.querySelector('input[name="prices[' + cycle + ']"]');
                if (!monthly || !target || monthly.value === '') {
                    return;
                }
                target.value = (Number(monthly.value) * (months[cycle] || 1) * Number(select.value)).toFixed(2);
            });
        });

        function syncOsLabel(select) {
            const row = select.closest('.option-row');
            const label = row?.querySelector('[data-os-label]');
            if (label) {
                label.value = select.options[select.selectedIndex]?.textContent?.trim() || select.value;
            }
        }

        document.querySelectorAll('[data-os-select]').forEach(syncOsLabel);
        document.addEventListener('change', function (event) {
            const select = event.target.closest('[data-os-select]');
            if (select) {
                syncOsLabel(select);
            }
        });
    </script>
@endpush
