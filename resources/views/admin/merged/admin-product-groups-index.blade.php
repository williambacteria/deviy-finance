
    <div class="page-head">
        <div><h1>商品分组</h1><p>支持一级、二级分组；可随时修改分组名称，商品按二级分组归类。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">返回商品设置</a>
    </div>

    @if (session('status'))
        <div class="note success" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <div class="panel" style="margin-bottom:16px">
        <div class="panel-head"><h2>新建分组</h2><span class="meta">最多支持两级</span></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.product-groups.store') }}" class="filter-bar" style="align-items:flex-end">
                @csrf
                <div class="form-field"><label class="form-label">分组名称 <span>*</span></label><input class="input" name="name" value="{{ old('name') }}" placeholder="例如：香港云服务器" required></div>
                <div class="form-field"><label class="form-label">上级分组</label><select class="select" name="parent_id"><option value="">无（一级分组）</option>@foreach ($groups as $root)<option value="{{ $root->id }}" @selected(old('parent_id') == $root->id)>{{ $root->name }}</option>@endforeach</select></div>
                <div class="form-field" style="min-width:260px;flex:1"><label class="form-label">分类说明</label><input class="input" name="description" value="{{ old('description') }}" placeholder="例如：KVM 架构、独立 IP、SSD 存储"></div>
                <div class="form-field"><label class="form-label">排序</label><input class="input" style="width:90px" type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}"></div>
                <div class="form-field"><label class="form-label">显示</label><select class="select" name="is_visible"><option value="1" @selected(old('is_visible', '1') === '1')>显示</option><option value="0" @selected(old('is_visible') === '0')>隐藏</option></select></div>
                <button class="btn btn-primary" type="submit">创建分组</button>
            </form>
        </div>
    </div>

    <form id="group-bulk-form" method="POST" action="{{ route('admin.product-groups.bulk-update') }}">
        @csrf
        @method('PUT')
    </form>

    <div class="panel">
        <div class="panel-head"><h2>分组结构</h2><span class="meta">拖动左侧 ⋮⋮ 调整顺序，修改后点击右下角统一保存</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>层级</th><th>分组名称</th><th>标识</th><th>商品</th><th>排序</th><th>状态</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                    @forelse ($groups as $root)
                        <tr class="group-sort-row" data-group-id="{{ $root->id }}" data-level="root" data-parent="">
                            <td class="group-level-cell">
                                <span class="group-drag-handle" draggable="true" data-group-drag title="拖动调整分组顺序">⋮⋮</span>
                                <span class="chip">一级</span>
                            </td>
                            <td>
                                <div class="group-bulk-fields">
                                    <input form="group-bulk-form" class="input" style="min-width:180px" name="groups[{{ $root->id }}][name]" value="{{ $root->name }}" required>
                                    <select form="group-bulk-form" class="select" name="groups[{{ $root->id }}][parent_id]" style="min-width:140px">
                                        <option value="">一级分组</option>
                                        @foreach ($groups as $option)
                                            @if ($option->id !== $root->id)
                                                <option value="{{ $option->id }}" @selected((int) $root->parent_id === $option->id)>{{ $option->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <input form="group-bulk-form" class="input" style="width:78px" type="number" min="0" name="groups[{{ $root->id }}][sort_order]" value="{{ $root->sort_order }}">
                                    <textarea form="group-bulk-form" rows="1" class="input group-bulk-desc" data-autosize name="groups[{{ $root->id }}][description]" placeholder="分类说明">{{ $root->description }}</textarea>
                                </div>
                            </td>
                            <td class="mono small">{{ $root->slug }}</td>
                            <td>{{ $root->products_count }}</td>
                            <td class="group-sort-display">{{ $root->sort_order }}</td>
                            <td><x-status :status="$root->is_visible ? 'active' : 'inactive'" :label="$root->is_visible ? '显示' : '隐藏'" /></td>
                            <td class="actions">
                                @if ($root->children->isEmpty() && $root->products_count === 0)
                                    <form method="POST" action="{{ route('admin.product-groups.destroy', $root) }}" data-confirm="确认删除该分组？">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                    </form>
                                @else
                                    <span class="small muted">含下级/商品</span>
                                @endif
                            </td>
                        </tr>
                        @foreach ($root->children as $child)
                            <tr class="group-sort-row" data-group-id="{{ $child->id }}" data-level="child" data-parent="{{ $root->id }}">
                                <td class="group-level-cell">
                                    <span class="group-drag-handle" draggable="true" data-group-drag title="拖动调整分组顺序">⋮⋮</span>
                                    <span class="chip" style="background:var(--dt-b-f1f5f9,#f1f5f9);color:var(--dt-t-64748b,#64748b)">二级</span>
                                </td>
                                <td style="padding-left:34px">
                                    <div class="small muted" style="margin-bottom:4px">{{ $root->name }} /</div>
                                    <div class="group-bulk-fields">
                                        <input form="group-bulk-form" class="input" style="min-width:180px" name="groups[{{ $child->id }}][name]" value="{{ $child->name }}" required>
                                        <select form="group-bulk-form" class="select" name="groups[{{ $child->id }}][parent_id]" style="min-width:140px">
                                            <option value="">一级分组</option>
                                            @foreach ($groups as $option)
                                                @if ($option->id !== $child->id)
                                                    <option value="{{ $option->id }}" @selected((int) $child->parent_id === $option->id)>{{ $option->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <input form="group-bulk-form" class="input" style="width:78px" type="number" min="0" name="groups[{{ $child->id }}][sort_order]" value="{{ $child->sort_order }}">
                                        <textarea form="group-bulk-form" rows="1" class="input group-bulk-desc" data-autosize name="groups[{{ $child->id }}][description]" placeholder="分类说明">{{ $child->description }}</textarea>
                                    </div>
                                </td>
                                <td class="mono small">{{ $child->slug }}</td>
                                <td>{{ $child->products_count }}</td>
                                <td class="group-sort-display">{{ $child->sort_order }}</td>
                                <td><x-status :status="$child->is_visible ? 'active' : 'inactive'" :label="$child->is_visible ? '显示' : '隐藏'" /></td>
                                <td class="actions">
                                    @if ($child->products_count === 0)
                                        <form method="POST" action="{{ route('admin.product-groups.destroy', $child) }}" data-confirm="确认删除该二级分组？">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                        </form>
                                    @else
                                        <span class="small muted">含商品</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="7" class="table-empty">还没有商品分组，请先创建一级分组。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button class="btn btn-primary group-save-float" type="submit" form="group-bulk-form">保存全部修改</button>

    @push('scripts')
        <script src="{{ asset('assets/js/product-group-sort.js') }}?v={{ filemtime(public_path('assets/js/product-group-sort.js')) }}" defer></script>
    @endpush

