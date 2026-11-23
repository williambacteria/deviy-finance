
    <div class="page-head">
        <div>
            <h1>首页商品模块</h1>
            <p class="muted">控制官网首页「选择适合你的云资源」展示的商品、标签和图标，最多 6 个。标签为空时首页只显示图标。</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.homepage-featured.update') }}">
        @csrf
        @method('PUT')

        <div class="panel">
            <div class="panel-head">
                <h2>展示商品</h2>
                <span>按从上到下的顺序展示</span>
            </div>
            <div class="panel-body">
                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;margin-bottom:18px;padding:14px;border:1px solid var(--dt-e-e5eaf3,#e5eaf3);border-radius:10px;background:var(--dt-b-f8faff,#f8faff)">
                    <label class="field">
                        <span>按分组筛选</span>
                        <select class="input" id="featured-group-filter">
                            <option value="">全部分组</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>搜索商品</span>
                        <input class="input" id="featured-product-search" type="search" placeholder="输入商品名称，例如 R99i">
                    </label>
                    <button class="btn" type="button" id="featured-refresh-products">刷新商品列表</button>
                </div>

                @php($icons = ['fire' => '热门火焰', 'spark' => '新上闪光', 'gift' => '活动礼盒', 'bolt' => '极速闪电', 'crown' => '尊享皇冠', 'tag' => '标签'])
                @for ($i = 0; $i < 6; $i++)
                    @php($item = $selected[$i] ?? null)
                    <div class="field-row" style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;align-items:end;margin-bottom:14px">
                        <label class="field">
                            <span>商品 {{ $i + 1 }}</span>
                            <select class="input" name="items[{{ $i }}][product_id]">
                                <option value="">不展示</option>
                                @foreach ($products->groupBy('product_group_id') as $groupProducts)
                                    @php($groupName = $groupProducts->first()->group?->name ?? '未分组')
                                    <optgroup label="{{ $groupName }}">
                                        @foreach ($groupProducts as $product)
                                            <option value="{{ $product->id }}"
                                                data-group-id="{{ $product->product_group_id }}"
                                                data-name="{{ $product->name }}"
                                                @selected((int) old("items.$i.product_id", $item['product_id'] ?? 0) === $product->id)>
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>标签</span>
                            <input class="input" type="text" maxlength="20" name="items[{{ $i }}][label]" value="{{ old("items.$i.label", $item['label'] ?? '') }}" placeholder="例如：热门 / 新上 / 活动 8 折">
                        </label>
                        <label class="field">
                            <span>图标</span>
                            <select class="input" name="items[{{ $i }}][icon]">
                                @foreach ($icons as $value => $label)
                                    <option value="{{ $value }}" @selected(old("items.$i.icon", $item['icon'] ?? 'fire') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endfor

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">保存首页商品</button>
                </div>
            </div>
        </div>
    </form>

    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            const groupFilter = document.getElementById('featured-group-filter');
            const searchInput = document.getElementById('featured-product-search');
            const refreshButton = document.getElementById('featured-refresh-products');
            const productSelects = Array.from(document.querySelectorAll('select[name$="[product_id]"]'));

            if (!groupFilter || !searchInput || !productSelects.length) {
                return;
            }

            const applyFilter = function () {
                const groupId = groupFilter.value;
                const keyword = searchInput.value.trim().toLowerCase();

                productSelects.forEach(function (select) {
                    select.querySelectorAll('optgroup').forEach(function (group) {
                        let visibleCount = 0;

                        group.querySelectorAll('option').forEach(function (option) {
                            if (!option.value) {
                                option.hidden = false;
                                visibleCount++;

                                return;
                            }

                            const matchesGroup = groupId === '' || option.dataset.groupId === groupId;
                            const matchesKeyword = keyword === '' || (option.dataset.name || '').toLowerCase().includes(keyword);
                            option.hidden = !(matchesGroup && matchesKeyword) && !option.selected;

                            if (!option.hidden) {
                                visibleCount++;
                            }
                        });

                        group.hidden = visibleCount === 0;
                    });
                });
            };

            groupFilter.addEventListener('change', applyFilter);
            searchInput.addEventListener('input', applyFilter);
            refreshButton?.addEventListener('click', function () {
                window.location.reload();
            });

            applyFilter();
        }());
    </script>

