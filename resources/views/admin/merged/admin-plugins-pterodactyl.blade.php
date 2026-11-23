
    <div class="page-head">
        <div>
            <div class="flex items-center gap-3"><h1>翼龙面板商品对接</h1><span class="chip">Pterodactyl</span></div>
            <p>连接翼龙面板，同步节点与 Eggs，将游戏服务器导入为商品并自动开通。</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-ghost" href="{{ route('admin.plugins.index') }}">返回插件</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif
    @if ($connectionError)
        <div class="note danger" style="margin-bottom:14px">连接失败：{{ $connectionError }}</div>
    @endif

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h2>面板连接</h2><span class="meta">Application API</span></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.pterodactyl.update') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="form-field full"><label class="form-label">面板地址 <span>*</span></label><input class="input" name="base_url" value="{{ old('base_url', $config['base_url'] ?? 'https://pl.deviy.cn') }}" placeholder="https://pl.deviy.cn" required></div>
                        <div class="form-field full"><label class="form-label">Application API Key <span>*</span></label><input class="input" type="password" name="api_key" value="{{ old('api_key', $config['api_key'] ?? '') }}" placeholder="ptla_..." autocomplete="new-password" required><div class="meta">用于读取节点、Eggs，创建用户和服务器。</div></div>
                        <div class="form-field full"><label class="form-label">Client API Key（可选）</label><input class="input" type="password" name="client_api_key" value="{{ old('client_api_key', $config['client_api_key'] ?? '') }}" placeholder="ptlc_..." autocomplete="new-password"><div class="meta">用于开机、关机、重启和重装。必须是面板管理员的客户端密钥。</div></div>
                        <div class="form-field full"><label class="flex items-center gap-2" style="font-size:12px"><input type="checkbox" name="is_enabled" value="1" @checked($plugin->is_enabled)> 启用翼龙面板插件</label></div>
                    </div>
                    <div class="flex items-center gap-3 mt-4">
                        <button class="btn btn-primary" type="submit">保存并测试连接</button>
                        <span class="small muted">连接正常后下方会显示节点和 Eggs。</span>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>面板资源</h2><span class="meta">实时读取</span></div>
            <div class="panel-body">
                @if ($nodes === [] && $nests === [])
                    <div class="small muted">保存 API 配置并连接成功后，这里会显示节点、Nest 和 Egg。</div>
                @else
                    <div class="form-field" style="margin-bottom:14px">
                        <label class="form-label">选择 Nest 查看 Eggs</label>
                        <select class="select" id="ptero-nest-view" data-view-endpoint="{{ route('admin.pterodactyl.index') }}">
                            <option value="">选择 Nest</option>
                            @foreach ($nests as $nest)
                                <option value="{{ data_get($nest, 'attributes.id') }}" @selected((int) $nestId === (int) data_get($nest, 'attributes.id'))>{{ data_get($nest, 'attributes.name') }} - #{{ data_get($nest, 'attributes.id') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="small muted" style="margin-bottom:6px">节点</div>
                    @forelse ($nodes as $node)
                        <div class="flex justify-between small" style="padding:5px 0"><span>{{ data_get($node, 'attributes.name') }}</span><span class="mono muted">#{{ data_get($node, 'attributes.id') }} - {{ data_get($node, 'attributes.fqdn') }}</span></div>
                    @empty
                        <div class="small muted">暂无节点。</div>
                    @endforelse
                @endif
            </div>
        </div>
    </div>

    @if ($nodes !== [] && $nests !== [])
        <div class="panel" style="margin-top:16px">
            <div class="panel-head"><h2>导入商品</h2><span class="meta">从 Egg 创建 Deviy 商品</span></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.pterodactyl.import') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="form-field"><label class="form-label">Nest</label><select class="select" name="nest_id" id="ptero-nest" data-eggs-endpoint="{{ route('admin.pterodactyl.eggs') }}" required><option value="">选择 Nest</option>@foreach ($nests as $nest)<option value="{{ data_get($nest, 'attributes.id') }}" @selected((int) $nestId === (int) data_get($nest, 'attributes.id'))>{{ data_get($nest, 'attributes.name') }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">Egg</label><select class="select" name="egg_id" id="ptero-egg" required><option value="">先选择 Nest</option>@foreach ($eggs as $egg)<option value="{{ data_get($egg, 'attributes.id') }}">{{ data_get($egg, 'attributes.name') }} - #{{ data_get($egg, 'attributes.id') }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">部署节点</label><select class="select" name="node_id" required><option value="">选择节点</option>@foreach ($nodes as $node)<option value="{{ data_get($node, 'attributes.id') }}">{{ data_get($node, 'attributes.name') }} - #{{ data_get($node, 'attributes.id') }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">商品名称</label><input class="input" name="name" value="{{ old('name') }}" placeholder="例如：Minecraft 基础版" required></div>
                        <div class="form-field"><label class="form-label">地区</label><input class="input" name="region" value="{{ old('region', 'default') }}" placeholder="例如：cn-suzhou"></div>
                        <div class="form-field"><label class="form-label">商品分组</label><select class="select" name="product_group_id"><option value="">自动创建「翼龙游戏服务器」</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected(old('product_group_id') == $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                        <div class="form-field"><label class="form-label">月付价格</label><input class="input" type="number" step="0.01" min="0.01" name="price" value="{{ old('price') }}" required></div>
                        <div class="form-field"><label class="form-label">库存</label><input class="input" type="number" min="0" name="stock" value="{{ old('stock', 99) }}" required></div>
                        <div class="form-field"><label class="form-label">内存 GB</label><input class="input" type="number" min="1" name="memory_gb" value="{{ old('memory_gb', 2) }}" required></div>
                        <div class="form-field"><label class="form-label">磁盘 GB</label><input class="input" type="number" min="1" name="disk_gb" value="{{ old('disk_gb', 10) }}" required></div>
                        <div class="form-field"><label class="form-label">CPU 核心</label><input class="input" type="number" min="1" name="cpu" value="{{ old('cpu', 1) }}" required></div>
                    </div>
                    <div class="note info" style="margin-top:12px">导入时会读取 Egg 的 Docker 镜像、启动命令和环境变量默认值。商品开通时将自动创建面板用户并创建服务器。</div>
                    <button class="btn btn-primary" type="submit" style="margin-top:14px">导入为商品</button>
                </form>
            </div>
        </div>
    @endif

    <div class="panel" style="margin-top:16px">
        <div class="panel-head"><h2>已导入商品</h2><span class="meta">{{ $products->count() }} 个</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>商品</th><th>编码</th><th>Nest / Egg / Node</th><th>月付</th><th>库存</th><th>状态</th></tr></thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td><b>{{ $product->name }}</b><div class="sub">{{ $product->region }}</div></td>
                                <td class="mono small">{{ $product->code }}</td>
                                <td class="mono small">{{ data_get($product->metadata, 'pterodactyl.nest_id') }} / {{ data_get($product->metadata, 'pterodactyl.egg_id') }} / {{ data_get($product->metadata, 'pterodactyl.node_id') }}</td>
                                <td>{{ $product->prices->firstWhere('billing_cycle', 'monthly')?->price ? 'Y'.number_format((float) $product->prices->firstWhere('billing_cycle', 'monthly')->price, 2) : '-' }}</td>
                                <td>{{ $product->stock }}</td>
                                <td><x-status :status="$product->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">还没有导入翼龙商品。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('assets/js/pterodactyl-admin.js') }}"></script>
    @endpush

