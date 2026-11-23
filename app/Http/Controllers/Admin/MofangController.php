<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductGroup;
use App\Models\UpstreamOrder;
use App\Models\UpstreamProduct;
use App\Models\UpstreamSupplier;
use App\Services\AuditLogger;
use App\Services\Mofang\MofangCatalogSync;
use App\Services\Mofang\MofangProductImporter;
use App\Services\Mofang\MofangSupplierTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class MofangController extends Controller
{
    public function index(Request $request): View
    {
        $category = trim((string) $request->query('category', ''));
        $categoryLabels = [
            'cloud' => '云服务器',
            'dcimcloud' => '魔方云',
            'server' => '物理服务器',
            'cdn' => 'CDN',
            'dcim' => 'DCIM',
            'hostingaccount' => '虚拟主机',
            'other' => '其他',
        ];
        $categoryCounts = UpstreamProduct::query()
            ->where('source', 'mofang')
            ->select('group_name')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('group_name')
            ->pluck('aggregate', 'group_name')
            ->all();
        arsort($categoryCounts);

        $categoryTree = [];
        $categoryOrder = [
            '海外云服务器' => 0,
            '大陆云服务器' => 1,
            '海外大宽带物理机-AI热门' => 2,
            '中国大陆物理机' => 3,
            'CDN' => 4,
            '托管|宿主机|增值服务' => 5,
        ];
        foreach (UpstreamProduct::query()->where('source', 'mofang')->get(['group_name', 'raw_payload']) as $item) {
            $parent = trim((string) data_get($item->raw_payload, 'category_parent', ''));
            $child = trim((string) data_get($item->raw_payload, 'category_name', ''));
            $parent = $parent !== '' ? $parent : '未归入上游侧边栏';
            $child = $child !== '' ? $child : ($item->group_name ?: '未分类');
            $categoryTree[$parent][$child] = ($categoryTree[$parent][$child] ?? 0) + 1;
        }
        uksort($categoryTree, fn (string $a, string $b): int => ($categoryOrder[$a] ?? 999) <=> ($categoryOrder[$b] ?? 999));

        $upstreams = UpstreamProduct::query()
            ->with('mappedProduct')
            ->when($category !== '', fn ($query) => $query->where('group_name', $category))
            ->orderBy('status')
            ->latest('synced_at')
            ->paginate(15)
            ->withQueryString();

        $upstreamOrders = UpstreamOrder::query()
            ->with(['service', 'order.user'])
            ->latest('id')
            ->limit(10)
            ->get();

        $suppliers = UpstreamSupplier::query()->latest('id')->get();
        $productGroups = ProductGroup::query()->orderBy('sort_order')->orderBy('name')->get();
        $activeSupplier = $suppliers->firstWhere('status', 'active') ?? $suppliers->first();

        return view('admin.mofang.index', [
            'upstreams' => $upstreams,
            'upstreamOrders' => $upstreamOrders,
            'suppliers' => $suppliers,
            'productGroups' => $productGroups,
            'category' => $category,
            'categoryLabels' => $categoryLabels,
            'categoryCounts' => $categoryCounts,
            'categoryTree' => $categoryTree,
            'driver' => $activeSupplier ? 'http' : config('mofang.driver'),
            'baseUrl' => $activeSupplier?->base_url ?: (config('mofang.base_url') ?: 'mock://local'),
            'defaultMarkup' => config('mofang.default_markup_percent'),
            'stats' => [
                'total' => UpstreamProduct::query()->where('source', 'mofang')->count(),
                'imported' => UpstreamProduct::query()->where('source', 'mofang')->where('status', 'imported')->count(),
                'available' => UpstreamProduct::query()->where('source', 'mofang')->where('status', 'available')->count(),
                'mapped' => UpstreamProduct::query()->where('source', 'mofang')->whereNotNull('mapped_product_id')->count(),
                'active_orders' => UpstreamOrder::query()->where('status', 'active')->count(),
                'failed_orders' => UpstreamOrder::query()->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function storeSupplier(Request $request, MofangSupplierTester $tester): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'driver' => ['required', 'in:mofang,manual'],
            'base_url' => ['required', 'url', 'max:255'],
            'username' => ['required', 'string', 'max:120'],
            'api_key' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'verify_ssl' => ['nullable', 'boolean'],
        ]);

        $data['verify_ssl'] = $request->boolean('verify_ssl');
        $supplier = UpstreamSupplier::create($data + ['status' => 'inactive']);

        try {
            $result = $tester->test($supplier);
            $supplier->update([
                'status' => 'active',
                'last_test_status' => 'success',
                'last_error' => null,
                'last_tested_at' => now(),
            ]);

            return back()->with('status', '供应商已添加，'.$result['message']);
        } catch (Throwable $exception) {
            $supplier->update([
                'status' => 'inactive',
                'last_test_status' => 'failed',
                'last_error' => $exception->getMessage(),
                'last_tested_at' => now(),
            ]);

            return back()->withErrors(['supplier' => '供应商已保存，但连接失败：'.$exception->getMessage()]);
        }
    }

    public function testSupplier(UpstreamSupplier $supplier, MofangSupplierTester $tester): RedirectResponse
    {
        try {
            $result = $tester->test($supplier);
            $supplier->update([
                'status' => 'active',
                'last_test_status' => 'success',
                'last_error' => null,
                'last_tested_at' => now(),
            ]);

            return back()->with('status', $result['message']);
        } catch (Throwable $exception) {
            $supplier->update([
                'status' => 'inactive',
                'last_test_status' => 'failed',
                'last_error' => $exception->getMessage(),
                'last_tested_at' => now(),
            ]);

            return back()->withErrors(['supplier' => '连接失败：'.$exception->getMessage()]);
        }
    }

    public function destroySupplier(UpstreamSupplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('status', '供应商已删除。');
    }

    public function sync(Request $request, MofangCatalogSync $sync, AuditLogger $audit): RedirectResponse
    {
        try {
            $count = $sync->sync();
        } catch (Throwable $exception) {
            Log::error('Mofang catalog sync failed', ['exception' => $exception]);

            return back()->withErrors(['mofang' => '同步失败：'.$exception->getMessage()]);
        }

        $audit->record($request->user('admin'), 'mofang.synced', '同步魔方上游商品：'.$count.' 条');

        return back()->with('status', '已同步 '.$count.' 条商品。');
    }

    public function import(
        Request $request,
        UpstreamProduct $upstream,
        MofangProductImporter $importer,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($upstream->source === 'mofang', 404);

        $data = $request->validate([
            'product_group_id' => ['required', 'integer', 'exists:product_groups,id'],
            'name' => ['required', 'string', 'max:120'],
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        try {
            $product = $importer->import(
                $upstream,
                (float) $data['markup_percent'],
                (int) $data['product_group_id'],
                (string) $data['name'],
            );
        } catch (Throwable $exception) {
            Log::error('Mofang product import failed', [
                'upstream_id' => $upstream->upstream_id,
                'exception' => $exception,
            ]);

            return back()->withErrors(['mofang' => '导入失败：'.$exception->getMessage()]);
        }

        $audit->record(
            $request->user('admin'),
            'mofang.imported',
            '导入上游商品并上架：'.$upstream->name,
            $product,
            ['upstream_id' => $upstream->upstream_id, 'markup_percent' => $data['markup_percent']],
        );

        return back()->with('status', '已导入并上架：'.$product->name);
    }

    public function bulkImport(
        Request $request,
        MofangProductImporter $importer,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'upstream_ids' => ['required', 'array', 'min:1', 'max:100'],
            'upstream_ids.*' => ['integer', 'distinct'],
            'product_group_id' => ['required', 'integer', 'exists:product_groups,id'],
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $upstreams = UpstreamProduct::query()
            ->where('source', 'mofang')
            ->whereIn('id', $data['upstream_ids'])
            ->get()
            ->reject(fn (UpstreamProduct $upstream): bool => $upstream->status === 'ignored');

        if ($upstreams->isEmpty()) {
            return back()->withErrors(['mofang_bulk' => '请选择至少一个可上架的上游商品。']);
        }

        $imported = 0;
        $failed = [];

        foreach ($upstreams as $upstream) {
            try {
                $importer->import(
                    $upstream,
                    (float) $data['markup_percent'],
                    (int) $data['product_group_id'],
                    $upstream->name,
                );
                $imported++;
            } catch (Throwable $exception) {
                Log::error('Mofang bulk product import failed', [
                    'upstream_id' => $upstream->upstream_id,
                    'exception' => $exception,
                ]);
                $failed[] = $upstream->name.'：'.$exception->getMessage();
            }
        }

        $audit->record(
            $request->user('admin'),
            'mofang.bulk_imported',
            '批量上架魔方上游商品：成功 '.$imported.' 个，失败 '.count($failed).' 个。',
            null,
            [
                'upstream_ids' => $upstreams->pluck('upstream_id')->values()->all(),
                'product_group_id' => $data['product_group_id'],
                'markup_percent' => $data['markup_percent'],
            ],
        );

        $message = '批量上架完成：成功 '.$imported.' 个商品。';
        if ($failed !== []) {
            $message .= ' 失败 '.count($failed).' 个：'.implode('；', array_slice($failed, 0, 3));
        }

        return back()->with('status', $message);
    }

    public function ignore(Request $request, UpstreamProduct $upstream, AuditLogger $audit): RedirectResponse
    {
        abort_unless($upstream->source === 'mofang', 404);

        $upstream->update(['status' => 'ignored']);
        $audit->record($request->user('admin'), 'mofang.ignored', '忽略上游商品：'.$upstream->name, $upstream);

        return back()->with('status', '已忽略该商品。');
    }

    public function retry(Request $request, UpstreamOrder $upstreamOrder, AuditLogger $audit): RedirectResponse
    {
        $upstreamOrder->update([
            'status' => $upstreamOrder->upstream_order_id ? 'ordered' : 'pending',
            'attempts' => 0,
            'last_error' => null,
        ]);

        $audit->record($request->user('admin'), 'mofang.retried', '重试上游订单 #'.$upstreamOrder->id, $upstreamOrder);

        return back()->with('status', '已重新加入魔方履约队列。');
    }
}
