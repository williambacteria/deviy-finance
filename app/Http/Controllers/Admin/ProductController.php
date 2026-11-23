<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPrice;
use App\Services\AuditLogger;
use App\Services\Provisioning\ControllerGateway;
use App\Services\Qzvps\QzvpsCatalog;
use App\Support\Billing;
use App\Support\ProductOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $groupId = (int) $request->integer('group_id');

        $groups = ProductGroup::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groupIds = [];
        if ($groupId > 0) {
            $selectedGroup = $groups->firstWhere('id', $groupId);
            $groupIds = $selectedGroup
                ? collect([$selectedGroup->id])->merge($selectedGroup->children->pluck('id'))->all()
                : [$groupId];
        }

        $products = Product::query()
            ->with(['group.parent', 'prices'])
            ->withCount(['services as sold_count'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when($groupIds !== [], fn ($query) => $query->whereIn('product_group_id', $groupIds))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $copyProducts = Product::query()->with('group.parent')->orderBy('sort_order')->orderBy('name')->orderBy('id')->get(['id', 'name', 'code', 'product_group_id', 'status']);
        $copyGroups = $copyProducts->groupBy(fn (Product $product) => $product->product_group_id ?? 0)->map(function ($items, $id): array {
            $group = $items->first()->group;
            return ['id' => (int) $id, 'root_id' => $group?->parent_id ?? $group?->id ?? 0, 'label' => $group ? ($group->parent ? $group->parent->name.' / '.$group->name : $group->name.' / 直属商品') : '未分组商品', 'products' => $items];
        })->sortBy('label')->values();

        return view('admin.products.index', compact('products', 'copyProducts', 'copyGroups', 'groups', 'search', 'status', 'type', 'groupId'));
    }

    public function sold(Product $product): View
    {
        $services = $product->services()
            ->with(['user' => fn ($query) => $query->withTrashed()])
            ->latest()
            ->paginate(20);
        $soldCount = $product->services()->count();

        return view('admin.products.sold', compact('product', 'services', 'soldCount'));
    }

    public function create(ControllerGateway $gateway): View
    {
        return view('admin.products.form', $this->formData(new Product, $gateway));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data) {
            $product = Product::create($data);
            $this->syncPrices($product, $data['prices'] ?? []);

            return $product;
        });

        $audit->record($request->user('admin'), 'product.created', '创建商品：'.$product->name, $product);

        return redirect()->route('admin.products.index')->with('status', '商品已创建。');
    }

    public function edit(Product $product, ControllerGateway $gateway): View
    {
        $product->load('prices');

        return view('admin.products.form', $this->formData($product, $gateway));
    }

    public function update(Request $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($product, $data) {
            $product->update($data);
            $this->syncPrices($product, $data['prices'] ?? []);
        });

        $audit->record($request->user('admin'), 'product.updated', '更新商品：'.$product->name, $product);

        return redirect()->route('admin.products.edit', $product)->with('status', '商品已保存。');
    }

    public function duplicate(Request $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        $copy = DB::transaction(function () use ($product): Product {
            $copy = $product->replicate();
            $copy->name = Str::limit($product->name.'（副本）', 120, '');
            $copy->code = $this->copyCode($product->code);
            $copy->status = 'draft';
            $copy->save();

            foreach ($product->prices as $price) {
                $copy->prices()->create($price->only(['billing_cycle', 'price', 'setup_fee', 'cost_price', 'is_active']));
            }

            return $copy;
        });

        $audit->record($request->user('admin'), 'product.duplicated', '复制商品：'.$product->name.' → '.$copy->name, $copy);

        return redirect()
            ->route('admin.products.edit', $copy)
            ->with('status', '商品已复制，请确认配置后上架。');
    }

    private function copyCode(string $sourceCode): string
    {
        $base = Str::limit($sourceCode, 70, '').'-COPY';
        $code = $base;
        $index = 2;

        while (Product::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$index++;
        }

        return $code;
    }

    public function destroy(Request $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        if ($product->services()->exists()) {
            return back()->withErrors(['product' => '该商品已有服务，不能删除；请改为下架。']);
        }

        $audit->record($request->user('admin'), 'product.deleted', '删除商品：'.$product->name, $product);
        $product->delete();

        return back()->with('status', '商品已删除。');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Product $product, ControllerGateway $gateway): array
    {
        $nodes = [];
        $images = [];
        $pools = [];
        $controllerError = null;

        try {
            $nodes = $gateway->nodes();
            $images = $gateway->images();
            $pools = $gateway->ipPools();
        } catch (Throwable $exception) {
            report($exception);
            $controllerError = '主控数据读取失败：'.$exception->getMessage();
        }

        $qzvpsCatalog = app(QzvpsCatalog::class);
        $qzvpsNodeId = (string) (data_get($product->metadata, 'qzvps.node_id') ?: '1');

        return [
            'product' => $product,
            'groups' => ProductGroup::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'optionDefinitions' => ProductOptions::DIMENSIONS,
            'optionRows' => ProductOptions::options($product),
            'optionSettings' => ProductOptions::settings($product),
            'cycles' => Billing::cycles(),
            'controllerNodes' => $nodes,
            'controllerImages' => $images,
            'controllerPools' => $pools,
            'controllerError' => $controllerError,
            'qzvpsLines' => $qzvpsCatalog->lines(),
            'qzvpsImages' => $qzvpsCatalog->imagesForNode($qzvpsNodeId),
            'qzvpsNodes' => $qzvpsCatalog->nodes(),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'product_group_id' => ['required', 'exists:product_groups,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:80', Rule::unique('products', 'code')->ignore($product?->id)],
            'type' => ['required', 'in:cloud_vm,nat_vm,game_server,bare_metal,cdn,dedicated_ip,bandwidth,other,colocation'],
            'upstream_source' => ['nullable', 'in:local,mofang,pterodactyl,qzvps,manual'],
            'region' => ['required', 'string', 'max:64'],
            'short_description' => ['nullable', 'string', 'max:200'],
            'card_intro' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'detail_page_content' => ['nullable', 'string'],
            'details' => ['nullable', 'array'],
            'details.*.label' => ['nullable', 'string', 'max:80'],
            'details.*.value' => ['nullable', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:draft,active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0'],
            'controller_node_id' => ['nullable', 'string', 'max:80'],
            'controller_public_host' => ['nullable', 'string', 'max:253', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/D'],
            'controller_image_id' => ['nullable', 'string', 'max:80'],
            'controller_ip_pool_id' => ['nullable', 'string', 'max:80'],
            'controller_ostype' => ['nullable', 'string', 'max:32'],
            'controller_network_mode' => ['nullable', 'in:public,private'],
            'qzvps_line_id' => ['nullable', 'string', 'max:40'],
            'qzvps_node_id' => ['nullable', 'string', 'max:40'],
            'qzvps_cpu' => ['nullable', 'integer', 'min:1', 'max:256'],
            'qzvps_memory_gb' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'qzvps_disk_gb' => ['nullable', 'integer', 'min:10', 'max:65536'],
            'qzvps_upload_mbps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'qzvps_download_mbps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'qzvps_os_name' => ['nullable', 'string', 'max:80'],
            'qzvps_ipnum' => ['nullable', 'integer', 'in:0,1'],
            'qzvps_port_num' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'qzvps_metal' => ['nullable', 'integer', 'in:0,1'],
            'qzvps_open_gpu' => ['nullable', 'integer', 'in:0,1'],
            'qzvps_mdevid' => ['nullable', 'string', 'max:20'],
            'default_cores' => ['nullable', 'integer', 'min:1', 'max:256'],
            'default_memory_gb' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'default_disk_gb' => ['nullable', 'integer', 'min:10', 'max:65536'],
            'default_data_disk_gb' => ['nullable', 'integer', 'min:0', 'max:65536'],
            'default_upload_mbps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'default_download_mbps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'expiry_reminder_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'array'],
            'options.*.*.value' => ['nullable', 'string', 'max:80'],
            'options.*.*.label' => ['nullable', 'string', 'max:80'],
            'options.*.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'option_settings' => ['nullable', 'array'],
            'option_settings.*.hint' => ['nullable', 'string', 'max:120'],
            'option_settings.*.range_enabled' => ['nullable', 'boolean'],
            'option_settings.*.range_min' => ['nullable', 'numeric', 'min:0'],
            'option_settings.*.range_max' => ['nullable', 'numeric', 'min:0'],
            'option_settings.*.range_step' => ['nullable', 'numeric', 'min:0.01'],
            'option_settings.*.range_unit_price' => ['nullable', 'numeric', 'min:0'],
            'cycle_discounts' => ['nullable', 'array'],
            'cycle_discounts.*' => ['nullable', 'numeric', 'min:0.1', 'max:1'],
        ]);

        $upstreamSource = (string) ($data['upstream_source'] ?? ($product?->upstream_source ?: 'local'));
        if ($data['status'] === 'active'
            && in_array($data['type'], ['cloud_vm', 'nat_vm'], true)
            && empty($data['controller_node_id'])
            && ! in_array($upstreamSource, ['mofang', 'pterodactyl', 'qzvps'], true)) {
            throw ValidationException::withMessages([
                'controller_node_id' => '上架云服务器或 NAT 云机前，必须选择宿主机主控节点。',
            ]);
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['upstream_source'] = $upstreamSource;
        $metadata = $product?->metadata ?? [];
        $metadata['controller'] = array_filter(array_merge((array)($metadata['controller']??[]), [
            'node_id' => $data['controller_node_id'] ?? null,
            'image_id' => $data['controller_image_id'] ?? null,
            'ip_pool_id' => $data['controller_ip_pool_id'] ?? null,
            'network_mode' => $data['controller_network_mode'] ?? null,
            'public_host' => $data['controller_public_host'] ?? data_get($metadata,'controller.public_host'),
        ]), fn ($value): bool => $value !== null && $value !== '');

        $metadata['options'] = ProductOptions::normalizeInput(
            is_array($request->input('options')) ? $request->input('options') : [],
            ProductOptions::options($product ?? new Product),
        );
        $firstOption = fn (string $key): int|string => $metadata['options'][$key][0]['value'] ?? 0;
        $metadata['specs'] = [
            'cpu' => (int) $firstOption('cpu'),
            'memory_gb' => (int) $firstOption('memory_gb'),
            'disk_gb' => (int) $firstOption('disk_gb'),
            'data_disk_gb' => (int) $firstOption('data_disk_gb'),
            'upload_mbps' => (int) $firstOption('upload_mbps'),
            'download_mbps' => (int) $firstOption('download_mbps'),
        ];
        $metadata['controller']['ostype'] = ProductOptions::controllerOSType((string) $firstOption('ostype'));
        if ($upstreamSource === 'qzvps') {
            $qzvpsNodeId = (string) ($data['qzvps_node_id'] ?? '');
            $allowedOs = (array) config('services.qzvps.os_by_node.'.$qzvpsNodeId, []);
            $selectedOs = (string) ($data['qzvps_os_name'] ?? '');
            if ($allowedOs !== [] && ! in_array($selectedOs, $allowedOs, true)) {
                throw ValidationException::withMessages([
                    'qzvps_os_name' => '该轻舟宿主机不支持所选操作系统，请从可用系统列表中选择。',
                ]);
            }
            $qzvpsSpecs = [
                'cpu' => (int) ($data['qzvps_cpu'] ?? data_get($metadata, 'specs.cpu', 2)),
                'memory_gb' => (int) ($data['qzvps_memory_gb'] ?? data_get($metadata, 'specs.memory_gb', 4)),
                'disk_gb' => (int) ($data['qzvps_disk_gb'] ?? data_get($metadata, 'specs.disk_gb', 40)),
                'upload_mbps' => (int) ($data['qzvps_upload_mbps'] ?? data_get($metadata, 'specs.upload_mbps', 10)),
                'download_mbps' => (int) ($data['qzvps_download_mbps'] ?? data_get($metadata, 'specs.download_mbps', 100)),
                'data_disk_gb' => 0,
                'bandwidth_mbps' => (int) ($data['qzvps_download_mbps'] ?? data_get($metadata, 'specs.download_mbps', 100)),
            ];
            $metadata['specs'] = $qzvpsSpecs;
            $metadata['qzvps'] = [
                'line_id' => (string) ($data['qzvps_line_id'] ?? ''),
                'node_id' => (string) ($data['qzvps_node_id'] ?? ''),
                'os_name' => (string) ($data['qzvps_os_name'] ?? 'Ubuntu22.04'),
                'ipnum' => (int) ($data['qzvps_ipnum'] ?? 0),
                'port_num' => (int) ($data['qzvps_port_num'] ?? 10),
                'metal' => (int) ($data['qzvps_metal'] ?? 0),
                'open_gpu' => (int) ($data['qzvps_open_gpu'] ?? 0),
                'mdevid' => (string) ($data['qzvps_mdevid'] ?? '1'),
            ];
            $qzvpsImages = app(QzvpsCatalog::class)->imagesForNode($qzvpsNodeId);
            if ($qzvpsImages !== []) {
                $metadata['options']['ostype'] = array_map(fn (array $image): array => [
                    'value' => (string) ($image['name'] ?? ''),
                    'label' => (string) ($image['name'] ?? ''),
                    'price' => 0,
                ], $qzvpsImages);
            }
        }
        $metadata['details'] = collect(is_array($data['details'] ?? null) ? $data['details'] : [])
            ->map(fn ($row): array => [
                'label' => trim((string) ($row['label'] ?? '')),
                'value' => trim((string) ($row['value'] ?? '')),
            ])
            ->filter(fn (array $row): bool => $row['label'] !== '' || $row['value'] !== '')
            ->values()
            ->all();
        $metadata['detail_page_content'] = trim((string) ($data['detail_page_content'] ?? ''));
        $metadata['card_intro'] = trim((string) ($data['card_intro'] ?? ''));

        $metadata['expiry_reminder_days'] = (int) ($data['expiry_reminder_days'] ?? 7);
        $metadata['option_settings'] = ProductOptions::normalizeSettings(
            is_array($data['option_settings'] ?? null) ? $data['option_settings'] : [],
            $product ?? new Product,
        );
        $metadata['cycle_discounts'] = collect(['quarterly', 'semiannually', 'annually'])
            ->mapWithKeys(fn (string $cycle): array => [
                $cycle => min(1, max(0.1, (float) ($data['cycle_discounts'][$cycle] ?? 1))),
            ])
            ->all();

        if ($product?->upstream_source === 'pterodactyl') {
            $originalMetadata = is_array($product->metadata) ? $product->metadata : [];
            $metadata['pterodactyl'] = $originalMetadata['pterodactyl'] ?? [];
            $metadata['specs'] = $originalMetadata['specs'] ?? $metadata['specs'];
        }

        $monthlyPrice = max(0, (float) ($data['prices']['monthly'] ?? 0));
        $data['prices'] = [
            'monthly' => $monthlyPrice,
            'quarterly' => round($monthlyPrice * 3 * $metadata['cycle_discounts']['quarterly'], 2),
            'semiannually' => round($monthlyPrice * 6 * $metadata['cycle_discounts']['semiannually'], 2),
            'annually' => round($monthlyPrice * 12 * $metadata['cycle_discounts']['annually'], 2),
        ];
        $data['metadata'] = $metadata;

        foreach ([
            'controller_public_host', 'controller_node_id', 'controller_image_id', 'controller_ip_pool_id', 'controller_ostype', 'controller_network_mode',
            'default_cores', 'default_memory_gb', 'default_disk_gb', 'default_data_disk_gb', 'default_upload_mbps', 'default_download_mbps',
            'qzvps_line_id', 'qzvps_node_id', 'qzvps_os_name', 'qzvps_ipnum', 'qzvps_port_num', 'qzvps_metal', 'qzvps_open_gpu', 'qzvps_mdevid',
            'qzvps_cpu', 'qzvps_memory_gb', 'qzvps_disk_gb', 'qzvps_upload_mbps', 'qzvps_download_mbps',
            'expiry_reminder_days', 'options', 'option_settings', 'details', 'detail_page_content', 'card_intro',
            'cycle_discounts',
        ] as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $prices
     */
    private function syncPrices(Product $product, array $prices): void
    {
        foreach (Billing::cycles() as $cycle => $meta) {
            $price = $prices[$cycle] ?? null;

            if ($price === null || $price === '') {
                continue;
            }

            ProductPrice::updateOrCreate(
                ['product_id' => $product->id, 'billing_cycle' => $cycle],
                ['price' => $price, 'is_active' => true],
            );
        }
    }
}
