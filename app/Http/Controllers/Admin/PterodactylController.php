<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPrice;
use App\Services\AuditLogger;
use App\Services\Pterodactyl\PterodactylSettings;
use App\Support\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PterodactylController extends Controller
{
    public function index(Request $request): View
    {
        $plugin = PterodactylSettings::plugin();
        $config = PterodactylSettings::configuration();
        $nodes = [];
        $nests = [];
        $eggs = [];
        $connectionError = null;
        $nestId = (int) $request->query('nest_id', 0);

        try {
            $client = PterodactylSettings::client();
            $nodes = $client->nodes();
            $nests = $client->nests();
            if ($nestId > 0) {
                $eggs = $client->eggs($nestId);
            }
        } catch (\Throwable $exception) {
            $connectionError = $exception->getMessage();
        }

        return view('admin.plugins.pterodactyl', [
            'plugin' => $plugin,
            'config' => $config,
            'nodes' => $nodes,
            'nests' => $nests,
            'eggs' => $eggs,
            'nestId' => $nestId,
            'connectionError' => $connectionError,
            'groups' => ProductGroup::query()->orderBy('name')->get(),
            'products' => Product::query()->where('upstream_source', 'pterodactyl')->latest('id')->get(),
            'cycles' => Billing::cycles(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'client_api_key' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $plugin = PterodactylSettings::plugin();
        $config = array_merge(PterodactylSettings::configuration(), [
            'base_url' => rtrim($data['base_url'], '/'),
            'api_key' => $data['api_key'],
            'client_api_key' => (string) ($data['client_api_key'] ?? ''),
        ]);
        $plugin->update([
            'config' => $config,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
        $audit->record($request->user('admin'), 'plugin.pterodactyl.configured', '更新翼龙面板对接配置', $plugin);

        try {
            $client = PterodactylSettings::client();
            $client->nodes();
        } catch (\Throwable $exception) {
            return back()->withErrors(['pterodactyl' => '配置已保存，但连接测试失败：'.$exception->getMessage()]);
        }

        return back()->with('status', '翼龙面板配置已保存，连接正常。');
    }

    public function eggs(Request $request): JsonResponse
    {
        $nestId = (int) $request->query('nest_id', 0);
        if ($nestId < 1) {
            return response()->json(['data' => []]);
        }

        try {
            return response()->json(['data' => PterodactylSettings::client()->eggs($nestId)]);
        } catch (\Throwable $exception) {
            return response()->json(['data' => [], 'error' => $exception->getMessage()], 422);
        }
    }

    public function import(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'nest_id' => ['required', 'integer', 'min:1'],
            'egg_id' => ['required', 'integer', 'min:1'],
            'node_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'memory_gb' => ['required', 'integer', 'min:1', 'max:1024'],
            'disk_gb' => ['required', 'integer', 'min:1', 'max:10240'],
            'cpu' => ['required', 'integer', 'min:1', 'max:128'],
            'stock' => ['required', 'integer', 'min:0'],
            'product_group_id' => ['nullable', 'integer', 'exists:product_groups,id'],
        ]);

        try {
            $client = PterodactylSettings::client();
            $eggPayload = $client->egg((int) $data['nest_id'], (int) $data['egg_id']);
            $egg = (array) data_get($eggPayload, 'attributes', []);
            if ($egg === []) {
                return back()->withErrors(['pterodactyl' => '读取 Egg 失败。']);
            }
            $environment = [];
            foreach ((array) data_get($egg, 'relationships.variables.data', []) as $variable) {
                $env = (string) (data_get($variable, 'attributes.env_variable') ?? data_get($variable, 'attributes.env') ?? '');
                if ($env !== '') {
                    $environment[$env] = (string) data_get($variable, 'attributes.default_value', '');
                }
            }
            $dockerImage = (string) (data_get($egg, 'docker_image') ?? '');
            $startup = (string) (data_get($egg, 'startup') ?? '');
            if ($dockerImage === '' || $startup === '') {
                return back()->withErrors(['pterodactyl' => 'Egg 缺少 Docker 镜像或启动命令。']);
            }
        } catch (\Throwable $exception) {
            return back()->withErrors(['pterodactyl' => '读取翼龙 Egg 失败：'.$exception->getMessage()]);
        }

        $group = ! empty($data['product_group_id'])
            ? ProductGroup::findOrFail($data['product_group_id'])
            : ProductGroup::firstOrCreate(
                ['slug' => 'pterodactyl'],
                ['name' => '翼龙游戏服务器', 'description' => '通过翼龙面板开通的游戏服务器商品', 'sort_order' => 10],
            );

        $upstreamId = $data['nest_id'].':'.$data['egg_id'].':'.$data['node_id'];
        $code = 'PTERO-'.Str::upper(Str::slug((string) data_get($egg, 'name', $data['name']), '-')).'-'.Str::upper(Str::random(4));

        $product = Product::updateOrCreate(
            ['upstream_source' => 'pterodactyl', 'upstream_product_id' => $upstreamId],
            [
                'product_group_id' => $group->id,
                'name' => $data['name'],
                'code' => $code,
                'type' => 'game_server',
                'region' => $data['region'] ?: 'default',
                'short_description' => Str::limit((string) data_get($egg, 'description', '翼龙面板游戏服务器'), 200, ''),
                'description' => (string) data_get($egg, 'description', ''),
                'stock' => $data['stock'],
                'status' => 'active',
                'sort_order' => 0,
                'metadata' => [
                    'upstream' => 'pterodactyl',
                    'pterodactyl' => [
                        'nest_id' => (int) $data['nest_id'],
                        'egg_id' => (int) $data['egg_id'],
                        'node_id' => (int) $data['node_id'],
                        'docker_image' => $dockerImage,
                        'startup' => $startup,
                        'environment' => $environment,
                        'limits' => [
                            'memory' => (int) $data['memory_gb'] * 1024,
                            'swap' => 0,
                            'disk' => (int) $data['disk_gb'] * 1024,
                            'io' => 500,
                            'cpu' => (int) $data['cpu'] * 100,
                        ],
                        'feature_limits' => ['databases' => 0, 'allocations' => 1, 'backups' => 0],
                    ],
                    'specs' => [
                        'cpu' => (int) $data['cpu'],
                        'memory_gb' => (int) $data['memory_gb'],
                        'disk_gb' => (int) $data['disk_gb'],
                    ],
                ],
            ],
        );

        foreach ($this->cyclePrices((float) $data['price']) as $cycle => $price) {
            ProductPrice::updateOrCreate(
                ['product_id' => $product->id, 'billing_cycle' => $cycle],
                ['price' => $price, 'cost_price' => 0, 'setup_fee' => 0, 'is_active' => true],
            );
        }

        $audit->record($request->user('admin'), 'plugin.pterodactyl.imported', '导入翼龙商品：'.$product->name, $product);

        return redirect()->route('admin.pterodactyl.index')->with('status', '翼龙商品已导入：'.$product->name);
    }

    /** @return array<string, string> */
    private function cyclePrices(float $monthly): array
    {
        $prices = [];
        foreach (Billing::cycles() as $cycle => $meta) {
            $prices[$cycle] = number_format($monthly * $meta['months'], 6, '.', '');
        }

        return $prices;
    }
}
