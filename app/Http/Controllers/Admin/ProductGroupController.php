<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductGroupController extends Controller
{
    public function index(): View
    {
        $groups = ProductGroup::query()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->withCount('products')
                    ->with(['products' => fn ($productQuery) => $productQuery->orderBy('sort_order')->orderBy('id')])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'products' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.product-groups.index', compact('groups'));
    }

    public function sortProducts(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:product_groups,id'],
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $group = ProductGroup::query()->findOrFail($data['group_id']);
        $products = Product::query()
            ->whereIn('id', $data['product_ids'])
            ->get()
            ->keyBy('id');

        foreach (array_values($data['product_ids']) as $index => $productId) {
            $product = $products->get((int) $productId);
            if (! $product) {
                continue;
            }
            $product->update([
                'product_group_id' => $group->id,
                'sort_order' => $index,
            ]);
        }

        $audit->record(
            $request->user('admin'),
            'product_group.products_sorted',
            '调整商品分组排序：'.$group->name,
            $group,
        );

        return response()->json(['ok' => true]);
    }

    public function bulkUpdate(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'groups' => ['required', 'array'],
            'groups.*.name' => ['required', 'string', 'max:80'],
            'groups.*.parent_id' => ['nullable', 'integer', 'exists:product_groups,id'],
            'groups.*.description' => ['nullable', 'string', 'max:255'],
            'groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $groups = ProductGroup::query()->withCount('children')->get()->keyBy('id');

        DB::transaction(function () use ($data, $groups): void {
            foreach ($data['groups'] as $id => $row) {
                $group = $groups->get((int) $id);
                if (! $group) {
                    continue;
                }

                $parentId = isset($row['parent_id']) && $row['parent_id'] !== ''
                    ? (int) $row['parent_id']
                    : null;

                if ($parentId !== null) {
                    $parent = $groups->get($parentId);
                    if (! $parent || $parentId === $group->id || $parent->parent_id !== null) {
                        throw ValidationException::withMessages(['groups' => '只能选择一级分组作为上级分组。']);
                    }
                    if ($group->children_count > 0) {
                        throw ValidationException::withMessages(['groups' => $group->name.' 下还有二级分组，不能改为二级分组。']);
                    }
                }

                $group->update([
                    'name' => $row['name'],
                    'parent_id' => $parentId,
                    'description' => $row['description'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ]);
            }
        });

        $audit->record($request->user('admin'), 'product_group.bulk_updated', '批量保存商品分组修改');

        return redirect()->route('admin.product-groups.index')->with('status', '分组修改已保存。');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_visible'] = $request->boolean('is_visible');

        $group = ProductGroup::create($data);
        $audit->record($request->user('admin'), 'product_group.created', '创建商品分组：'.$group->name, $group);

        return back()->with('status', '分组已创建。');
    }

    public function update(Request $request, ProductGroup $group, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent_id' => ['nullable', 'integer', 'exists:product_groups,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId === $group->id) {
            throw ValidationException::withMessages(['parent_id' => '不能把分组设为自己的上级。']);
        }
        if ($parentId !== null) {
            $parent = ProductGroup::query()->find($parentId);
            if ($parent?->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => '二级分组下不能再创建下级分组。']);
            }
        }
        if ($parentId !== null && $group->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => '含下级分组的分组不能移动到其他分组下。']);
        }

        $group->update([
            'name' => $data['name'],
            'parent_id' => $parentId,
            'sort_order' => $data['sort_order'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);
        $audit->record($request->user('admin'), 'product_group.updated', '修改商品分组：'.$group->name, $group);

        return back()->with('status', '分组名称已更新。');
    }

    public function destroy(Request $request, ProductGroup $group, AuditLogger $audit): RedirectResponse
    {
        if ($group->children()->exists()) {
            return back()->withErrors(['group' => '该分组下还有二级分组，请先删除二级分组。']);
        }

        if ($group->products()->exists()) {
            return back()->withErrors(['group' => '该分组下还有商品，不能删除。']);
        }

        $audit->record($request->user('admin'), 'product_group.deleted', '删除商品分组：'.$group->name, $group);
        $group->delete();

        return back()->with('status', '分组已删除。');
    }

    /**
     * @return array{name: string, parent_id: int|null, sort_order: int|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent_id' => ['nullable', 'integer', 'exists:product_groups,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId !== null) {
            $parent = ProductGroup::query()->find($parentId);
            if ($parent?->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => '二级分组下不能再创建下级分组。']);
            }
        }

        return [
            'name' => $data['name'],
            'parent_id' => $parentId,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            'description' => $data['description'] ?? null,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'group';
        $slug = $base;
        $suffix = 1;

        while (ProductGroup::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
