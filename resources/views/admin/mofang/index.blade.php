@extends('layouts.admin')

@section('title', '魔方选品')
@section('page-title', '魔方选品')

@section('content')
    <div class="page-head">
        <div><h1>魔方选品</h1><p>从魔方财务上游同步商品，设置加价后一键上架到本地商城。</p></div>
        <div class="page-actions">
            <form method="POST" action="{{ route('admin.mofang.sync') }}">
                @csrf
                <button class="btn btn-primary" type="submit"><x-icon name="refresh" :size="15" /> 同步上游商品</button>
            </form>
            <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">查看本地商品</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-top"><span class="stat-label">上游商品</span><span class="stat-icon"><x-icon name="cloud" :size="17" /></span></div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-foot">来自魔方财务</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">待选品</span><span class="stat-icon"><x-icon name="package" :size="17" /></span></div><div class="stat-value">{{ $stats['available'] }}</div><div class="stat-foot">可设置加价并上架</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">已导入</span><span class="stat-icon"><x-icon name="check" :size="17" /></span></div><div class="stat-value">{{ $stats['imported'] }}</div><div class="stat-foot">已生成本地商品</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">已映射</span><span class="stat-icon"><x-icon name="network" :size="17" /></span></div><div class="stat-value">{{ $stats['mapped'] }}</div><div class="stat-foot">本地商品与上游 ID 绑定</div></div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head">
            <h2>上游供应商</h2>
            <span class="meta">智简魔方 API · 添加后自动测试连接</span>
        </div>
        <div class="panel-body">
            @if ($errors->has('supplier'))
                <div class="note danger" style="margin-bottom:14px">{{ $errors->first('supplier') }}</div>
            @endif
            @if ($errors->has('mofang_bulk'))
                <div class="note danger" style="margin-bottom:14px">{{ $errors->first('mofang_bulk') }}</div>
            @endif
            @if (session('status'))
                <div class="note success" style="margin-bottom:14px">{{ session('status') }}</div>
            @endif

            @forelse ($suppliers as $supplier)
                <div class="flex items-center justify-between gap-3" style="padding:10px 0;border-bottom:1px solid var(--dt-e-eef2f8,#eef2f8);flex-wrap:wrap">
                    <div>
                        <b>{{ $supplier->name }}</b>
                        <span class="chip" style="margin-left:6px">{{ $supplier->driver === 'mofang' ? '智简魔方' : '手动' }}</span>
                        <x-status :status="$supplier->status === 'active' ? 'active' : 'inactive'" :label="$supplier->status === 'active' ? '已连接' : '未连接'" />
                        <div class="small muted" style="margin-top:4px">{{ $supplier->base_url }} · {{ $supplier->username }}</div>
                        @if ($supplier->last_error)
                            <div class="small" style="color:var(--dt-t-dc2626,#dc2626);margin-top:4px">{{ $supplier->last_error }}</div>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.mofang.suppliers.test', $supplier) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">测试连接</button></form>
                        <form method="POST" action="{{ route('admin.mofang.suppliers.destroy', $supplier) }}" onsubmit="return confirm('确认删除该供应商？')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" type="submit">删除</button></form>
                    </div>
                </div>
            @empty
                <div class="note" style="margin-bottom:14px">还没有供应商，请添加上游接口。</div>
            @endforelse

            <details style="margin-top:14px" @if ($suppliers->isEmpty()) open @endif>
                <summary class="btn btn-ghost btn-sm" style="cursor:pointer;display:inline-flex">+ 添加供应商</summary>
                <form method="POST" action="{{ route('admin.mofang.suppliers.store') }}" class="form-grid" style="margin-top:14px;max-width:720px">
                    @csrf
                    <div class="form-field"><label class="form-label">名称 <span>*</span></label><input class="input" name="name" value="{{ old('name') }}" placeholder="例如 TianLiYun" required></div>
                    <div class="form-field"><label class="form-label">联系方式</label><input class="input" name="contact" value="{{ old('contact') }}" placeholder="可选"></div>
                    <div class="form-field full"><label class="form-label">备注</label><textarea class="textarea" name="note" rows="2">{{ old('note') }}</textarea></div>
                    <div class="form-field"><label class="form-label">接口类型 <span>*</span></label><select class="select" name="driver" required><option value="mofang" @selected(old('driver', 'mofang') === 'mofang')>智简魔方</option><option value="manual" @selected(old('driver') === 'manual')>手动</option></select></div>
                    <div class="form-field"><label class="form-label">接口地址 <span>*</span></label><input class="input" name="base_url" value="{{ old('base_url') }}" placeholder="https://tianliyun.cn" required></div>
                    <div class="form-field"><label class="form-label">用户名 <span>*</span></label><input class="input" name="username" value="{{ old('username') }}" placeholder="上游账号/手机号" required></div>
                    <div class="form-field"><label class="form-label">API 密钥 <span>*</span></label><input class="input" type="password" name="api_key" placeholder="上游安全中心 API 密钥" required autocomplete="new-password"></div>
                    <div class="form-field"><label class="form-label">上游登录密码</label><input class="input" type="password" name="password" placeholder="用于购物车下单，选填" autocomplete="new-password"></div>
                    <div class="form-field full">
                        <input type="hidden" name="verify_ssl" value="0">
                        <label class="flex items-center gap-2" style="font-size:12px;color:var(--dt-t-64748b,#64748b)"><input type="checkbox" name="verify_ssl" value="1" @checked(old('verify_ssl'))> 跳过 SSL 证书校验（上游证书链异常时使用）</label>
                    </div>
                    <div class="form-field full flex gap-2"><button class="btn btn-primary" type="submit">保存并测试</button><span class="meta">保存后会自动调用上游商品接口验证。</span></div>
                </form>
            </details>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head">
            <h2>上游连接</h2>
            <span class="badge {{ $driver === 'http' ? 'active' : 'info' }}">{{ $driver === 'http' ? 'HTTP 模式' : 'Mock 模式' }}</span>
        </div>
        <div class="panel-body">
            <div class="grid-3">
                <div class="note info">
                    <b>接口地址</b><br>{{ $baseUrl }}
                </div>
                <div class="note">
                    <b>默认加价</b><br>{{ $defaultMarkup }}% · 可在导入时单独调整
                </div>
                <div class="note">
                    <b>同步周期</b><br>每小时自动同步 · 也可手动触发
                </div>
            </div>
            <div class="note warn mt-3">
                当前为 <b>{{ $driver === 'mock' ? 'Mock 演示模式' : 'HTTP 真实模式' }}</b>。生产环境请在 <span class="mono">.env</span> 配置
                <span class="mono">MOFANG_DRIVER=http</span>、上游地址、用户名与 API 密钥，并按上游版本确认接口路径与签名方式。
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>上游商品</h2><span class="meta">{{ $upstreams->total() }} 条</span></div>
        <div class="panel-body tight">
            <div class="mofang-catalog-layout">
                <aside class="mofang-category-pane">
                    <div class="mofang-category-head">上游商品分类</div>
                    <div class="mofang-category-nav">
                        <a class="mofang-category-all {{ $category === '' ? 'active' : '' }}" href="{{ route('admin.mofang.index') }}">
                            <span>全部商品</span><b>{{ $stats['total'] }}</b>
                        </a>
                        @foreach ($categoryTree as $parentName => $children)
                            @php($parentOpen = $category !== '' && array_key_exists($category, $children))
                            <div class="mofang-category-group">
                                <button class="mofang-category-parent {{ $parentOpen ? 'open' : '' }}" type="button" data-mofang-category-toggle aria-expanded="{{ $parentOpen ? 'true' : 'false' }}">
                                    <span>{{ $parentName }}</span>
                                    <b>{{ array_sum($children) }}</b>
                                    <i class="mofang-category-chevron">⌄</i>
                                </button>
                                <div class="mofang-category-children" @if (! $parentOpen) hidden @endif>
                                    @foreach ($children as $childName => $childCount)
                                        <a class="mofang-category-child {{ $category === $childName ? 'active' : '' }}" href="{{ route('admin.mofang.index', ['category' => $childName]) }}">
                                            <span>{{ $childName }}</span><b>{{ $childCount }}</b>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </aside>
                <div class="mofang-catalog-main">
                    <div class="mofang-catalog-title">
                        <b>{{ $category !== '' ? $category : '全部上游商品' }}</b>
                        @if ($category !== '')
                            <a class="small" style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('admin.mofang.index') }}">清除筛选</a>
                        @endif
                    </div>
            <form id="mofang-bulk-form" method="POST" action="{{ route('admin.mofang.bulk-import') }}">
                @csrf
                <div class="flex items-center" style="gap:10px;flex-wrap:wrap;padding:12px 14px;border-bottom:1px solid var(--dt-e-eef2f8,#eef2f8);background:var(--dt-b-f8fafc,#f8fafc)">
                    <label class="flex items-center gap-2" style="font-size:13px;font-weight:600;color:var(--dt-t-334155,#334155)">
                        <input type="checkbox" data-mofang-select-all>
                        全选本页
                    </label>
                    <span class="small muted" data-mofang-selected-count>已选择 0 项</span>
                    <span style="width:1px;height:20px;background:var(--dt-b-e2e8f0,#e2e8f0)"></span>
                    <select class="select" name="product_group_id" required style="width:240px;min-width:200px;max-width:280px">
                        <option value="">选择上架分组</option>
                        @foreach ($productGroups->whereNull('parent_id') as $rootGroup)
                            <option value="{{ $rootGroup->id }}">{{ $rootGroup->name }}</option>
                            @foreach ($productGroups->where('parent_id', $rootGroup->id) as $childGroup)
                                <option value="{{ $childGroup->id }}">　└ {{ $childGroup->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 small muted">
                        加价
                        <input class="input" type="number" name="markup_percent" value="{{ $defaultMarkup }}" min="0" max="500" step="0.01" style="width:90px" required>
                        %
                    </label>
                    <button class="btn btn-primary btn-sm" type="submit" data-mofang-bulk-submit disabled>批量上架</button>
                    <span class="meta">勾选多个上游商品后一次性导入并上架</span>
                </div>
            </form>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th style="width:34px"></th><th>商品</th><th>地域</th><th>上游价格</th><th>库存</th><th>状态</th><th>本地商品</th><th>加价 / 操作</th></tr></thead>
                    <tbody>
                        @forelse ($upstreams as $upstream)
                            @php($upstreamStock = (int) data_get($upstream->raw_payload, 'stock', 0))
                            @php($categoryParent = (string) data_get($upstream->raw_payload, 'category_parent', ''))
                            @php($categoryName = (string) data_get($upstream->raw_payload, 'category_name', $upstream->group_name))
                            <tr>
                                <td>
                                    <input type="checkbox"
                                           form="mofang-bulk-form"
                                           name="upstream_ids[]"
                                           value="{{ $upstream->id }}"
                                           data-mofang-select
                                           @disabled($upstream->status === 'ignored' || $upstreamStock <= 0)>
                                </td>
                                <td>
                                    <span class="strong">{{ $upstream->name }}</span>
                                    <div class="sub mono">{{ $upstream->upstream_id }}</div>
                                    <div class="sub">{{ $categoryParent !== '' ? $categoryParent.' / ' : '' }}{{ $categoryName ?: '未分类' }}</div>
                                </td>
                                <td class="small">{{ $upstream->region ?: '—' }}</td>
                                <td class="small">
                                    @foreach ($upstream->prices as $cycle => $price)
                                        <span class="chip" style="margin:2px 2px 2px 0">{{ \App\Support\Billing::label($cycle) }} ¥{{ number_format((float) $price, 2) }}</span>
                                    @endforeach
                                </td>
                                <td class="small">
                                    @if ($upstreamStock <= 0)
                                        <span class="chip" style="border-color:var(--dt-e-fecaca,#fecaca);background:var(--dt-b-fef2f2,#fef2f2);color:var(--dt-t-b91c1c,#b91c1c)">已售罄</span>
                                    @else
                                        <span class="muted">库存 {{ $upstreamStock }}</span>
                                    @endif
                                </td>
                                <td>
                                    <x-status :status="$upstream->status" :label="['available' => '待选品', 'imported' => '已导入', 'ignored' => '已忽略'][$upstream->status] ?? $upstream->status" />
                                </td>
                                <td class="small">
                                    @if ($upstream->mappedProduct)
                                        <a style="color:var(--dt-t-2563eb,#2563eb)" href="{{ route('admin.products.edit', $upstream->mappedProduct) }}">{{ $upstream->mappedProduct->name }}</a>
                                        <div class="sub">加价 {{ $upstream->markup_percent }}%</div>
                                    @else
                                        <span class="muted">未映射</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($upstreamStock <= 0)
                                        <span class="chip" style="border-color:var(--dt-e-fecaca,#fecaca);background:var(--dt-b-fef2f2,#fef2f2);color:var(--dt-t-b91c1c,#b91c1c)">已售罄</span>
                                    @elseif ($upstream->status !== 'ignored')
                                        <button class="btn btn-primary btn-sm" type="button"
                                            data-import-open
                                            data-action="{{ route('admin.mofang.import', $upstream) }}"
                                            data-name="{{ $upstream->name }}"
                                            data-markup="{{ $upstream->markup_percent ?? $defaultMarkup }}">
                                            {{ $upstream->mapped_product_id ? '重新上架' : '导入上架' }}
                                        </button>
                                    @else
                                        <span class="chip">已忽略</span>
                                    @endif
                                    @if (! $upstream->mapped_product_id && $upstream->status !== 'ignored' && $upstreamStock > 0)
                                        <form method="POST" action="{{ route('admin.mofang.ignore', $upstream) }}" style="display:inline" data-confirm="确认忽略该上游商品？">
                                            @csrf
                                            <button class="btn btn-ghost btn-sm" type="submit">忽略</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">暂无上游商品。点击“同步上游商品”拉取。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $upstreams->links('partials.pagination') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head">
            <h2>上游履约</h2>
            <span class="meta">成功 {{ $stats['active_orders'] }} · 失败 {{ $stats['failed_orders'] }}</span>
        </div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>本地服务</th><th>客户</th><th>上游订单</th><th>状态</th><th>尝试</th><th>最后错误</th><th>操作</th></tr></thead>
                    <tbody>
                        @forelse ($upstreamOrders as $upstreamOrder)
                            <tr>
                                <td>
                                    <span class="strong">{{ $upstreamOrder->service?->name ?? '—' }}</span>
                                    <div class="sub mono">{{ $upstreamOrder->service?->service_no }} · {{ $upstreamOrder->type === 'renewal' ? '续费' : '新购' }}</div>
                                </td>
                                <td class="small">{{ $upstreamOrder->order?->user?->name }}<div class="sub">{{ $upstreamOrder->order?->order_no }}</div></td>
                                <td class="small mono">
                                    {{ $upstreamOrder->upstream_order_id ?: '待下单' }}
                                    <div class="sub">{{ $upstreamOrder->upstream_service_id ?: '—' }}</div>
                                </td>
                                <td><x-status :status="$upstreamOrder->status" :label="['pending' => '待下单', 'ordered' => '已下单', 'paid' => '上游已付', 'provisioning' => '开通中', 'active' => '已开通', 'failed' => '履约失败'][$upstreamOrder->status] ?? $upstreamOrder->status" /></td>
                                <td class="small">{{ $upstreamOrder->attempts }}</td>
                                <td class="small muted" style="max-width:260px">{{ $upstreamOrder->last_error ? \Illuminate\Support\Str::limit($upstreamOrder->last_error, 48) : '—' }}</td>
                                <td>
                                    @if ($upstreamOrder->status === 'failed')
                                        <form method="POST" action="{{ route('admin.mofang.retry', $upstreamOrder) }}">
                                            @csrf
                                            <button class="btn btn-soft btn-sm" type="submit">重试</button>
                                        </form>
                                    @else
                                        <span class="muted small">自动处理</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">暂无上游订单。购买魔方商品并支付后自动进入履约队列。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="mofang-import-modal" aria-hidden="true">
        <div class="modal-card" style="width:min(520px,100%)">
            <div class="modal-head"><h2>导入上游商品</h2><button class="modal-close" type="button" data-import-close>×</button></div>
            <div class="modal-body">
                <form method="POST" action="" id="mofang-import-form">
                    @csrf
                    <div class="form-field">
                        <label class="form-label">商品名称 <span>*</span></label>
                        <input class="input" name="name" id="mofang-import-name" required>
                    </div>
                    <div class="form-field mt-3">
                        <label class="form-label">商品分组 <span>*</span></label>
                        @php($rootGroups = $productGroups->whereNull('parent_id'))
                        @php($childGroups = $productGroups->whereNotNull('parent_id')->groupBy('parent_id'))
                        <div class="import-group-picker" id="import-group-picker">
                            <button class="input import-group-trigger" type="button" data-import-group-trigger>
                                <span data-import-group-label>请选择商品分组</span><span>▾</span>
                            </button>
                            <div class="import-group-panel" data-import-group-panel hidden>
                                @foreach ($rootGroups as $rootGroup)
                                    @php($children = $childGroups->get($rootGroup->id, collect()))
                                    @if ($children->isNotEmpty())
                                        <div class="import-group-root">
                                            <button class="import-group-root-toggle" type="button" data-import-root-toggle>
                                                <span>{{ $rootGroup->name }}</span><span>›</span>
                                            </button>
                                            <div class="import-group-children" hidden>
                                                @foreach ($children as $childGroup)
                                                    <button class="import-group-option" type="button" data-import-group-choice data-group-id="{{ $childGroup->id }}" data-group-name="{{ $childGroup->name }}">{{ $childGroup->name }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <button class="import-group-option" type="button" data-import-group-choice data-group-id="{{ $rootGroup->id }}" data-group-name="{{ $rootGroup->name }}">{{ $rootGroup->name }}</button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <input type="hidden" name="product_group_id" id="mofang-import-group" value="">
                        <div class="small" data-import-group-error style="color:var(--dt-t-dc2626,#dc2626);margin-top:5px;display:none">请选择商品分组</div>
                    </div>
                    <div class="form-field mt-3">
                        <label class="form-label">加价百分比 <span>*</span></label>
                        <input class="input" name="markup_percent" id="mofang-import-markup" type="number" min="0" max="500" step="0.01" required>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">导入并上架</button>
                        <button class="btn btn-ghost" type="button" data-import-close>取消</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            const modal = document.getElementById('mofang-import-modal');
            const form = document.getElementById('mofang-import-form');
            const name = document.getElementById('mofang-import-name');
            const markup = document.getElementById('mofang-import-markup');
            const groupInput = document.getElementById('mofang-import-group');
            const groupTrigger = document.querySelector('[data-import-group-trigger]');
            const groupLabel = document.querySelector('[data-import-group-label]');
            const groupPanel = document.querySelector('[data-import-group-panel]');
            const groupError = document.querySelector('[data-import-group-error]');
            const groupPicker = document.getElementById('import-group-picker');

            document.querySelectorAll('[data-mofang-category-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const panel = button.nextElementSibling;
                    if (!panel) {
                        return;
                    }
                    const open = panel.hidden;
                    panel.hidden = !open;
                    button.classList.toggle('open', open);
                    button.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            });

            function resetGroupPicker() {
                groupInput.value = '';
                groupLabel.textContent = '请选择商品分组';
                groupPanel.hidden = true;
                groupError.style.display = 'none';
                groupPanel.querySelectorAll('.import-group-children').forEach(function (children) {
                    children.hidden = true;
                });
                groupPanel.querySelectorAll('[data-import-root-toggle]').forEach(function (button) {
                    button.classList.remove('open');
                });
            }

            groupTrigger.addEventListener('click', function () {
                groupPanel.hidden = !groupPanel.hidden;
            });

            groupPanel.querySelectorAll('[data-import-root-toggle]').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const children = button.nextElementSibling;
                    if (!children) return;
                    children.hidden = !children.hidden;
                    button.classList.toggle('open', !children.hidden);
                });
            });

            groupPanel.querySelectorAll('[data-import-group-choice]').forEach(function (button) {
                button.addEventListener('click', function () {
                    groupInput.value = button.dataset.groupId || '';
                    groupLabel.textContent = button.dataset.groupName || '';
                    groupPanel.hidden = true;
                    groupError.style.display = 'none';
                });
            });

            document.addEventListener('click', function (event) {
                if (!groupPicker.contains(event.target)) {
                    groupPanel.hidden = true;
                }
            });

            document.querySelectorAll('[data-import-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    form.action = button.dataset.action;
                    name.value = button.dataset.name || '';
                    markup.value = button.dataset.markup || '20';
                    resetGroupPicker();
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                });
            });

            form.addEventListener('submit', function (event) {
                if (groupInput.value === '') {
                    event.preventDefault();
                    groupError.style.display = 'block';
                    groupPanel.hidden = false;
                }
            });

            function close() {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('[data-import-close]').forEach(function (button) {
                button.addEventListener('click', close);
            });
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    close();
                }
            });

            const bulkForm = document.getElementById('mofang-bulk-form');
            if (bulkForm) {
                const selectAll = bulkForm.querySelector('[data-mofang-select-all]');
                const selectedCount = bulkForm.querySelector('[data-mofang-selected-count]');
                const bulkSubmit = bulkForm.querySelector('[data-mofang-bulk-submit]');
                const boxes = Array.from(document.querySelectorAll('[data-mofang-select]')).filter(function (box) {
                    return !box.disabled;
                });

                function refreshSelection() {
                    const selected = boxes.filter(function (box) {
                        return box.checked;
                    });
                    if (selectedCount) {
                        selectedCount.textContent = '已选择 ' + selected.length + ' 项';
                    }
                    if (bulkSubmit) {
                        bulkSubmit.disabled = selected.length === 0;
                    }
                    if (selectAll) {
                        selectAll.checked = boxes.length > 0 && selected.length === boxes.length;
                        selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
                    }
                }

                selectAll?.addEventListener('change', function () {
                    boxes.forEach(function (box) {
                        box.checked = selectAll.checked;
                    });
                    refreshSelection();
                });

                boxes.forEach(function (box) {
                    box.addEventListener('change', refreshSelection);
                });

                bulkForm.addEventListener('submit', function (event) {
                    const selected = boxes.filter(function (box) {
                        return box.checked;
                    });
                    if (selected.length === 0) {
                        event.preventDefault();
                    }
                });

                refreshSelection();
            }
        })();
    </script>
@endpush
