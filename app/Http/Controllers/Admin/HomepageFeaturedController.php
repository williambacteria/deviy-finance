<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class HomepageFeaturedController extends Controller
{
    public function index(): View|Response
    {
        $products = Product::query()
            ->with('group')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groups = $products
            ->pluck('group')
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->values();

        $selected = $this->selectedItems();

        return response()
            ->view('admin.homepage-featured.index', compact('products', 'groups', 'selected'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:6'],
            'items.*.product_id' => ['nullable', 'integer', 'distinct', 'exists:products,id'],
            'items.*.label' => ['nullable', 'string', 'max:20'],
            'items.*.icon' => ['nullable', 'in:fire,spark,gift,bolt,crown,tag'],
        ]);

        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            if (empty($item['product_id'])) {
                continue;
            }
            $items[] = [
                'product_id' => (int) $item['product_id'],
                'label' => trim((string) ($item['label'] ?? '')),
                'icon' => (string) ($item['icon'] ?? 'fire'),
            ];
        }

        Setting::query()->updateOrCreate(
            ['key' => 'homepage_featured_products'],
            ['group_name' => 'homepage', 'value' => json_encode($items, JSON_UNESCAPED_UNICODE), 'type' => 'json'],
        );

        return back()->with('status', '首页商品模块已保存。');
    }

    /** @return array<int, array{product_id:int,label:string,icon:string}> */
    private function selectedItems(): array
    {
        $raw = (string) Setting::value('homepage_featured_products', '[]');
        $items = json_decode($raw, true);

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item): ?array {
            if (! is_array($item) || empty($item['product_id'])) {
                return null;
            }

            return [
                'product_id' => (int) $item['product_id'],
                'label' => (string) ($item['label'] ?? ''),
                'icon' => (string) ($item['icon'] ?? 'fire'),
            ];
        }, $items)));
    }
}
