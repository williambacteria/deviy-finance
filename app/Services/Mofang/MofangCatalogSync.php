<?php

namespace App\Services\Mofang;

use App\Models\UpstreamProduct;
use App\Services\Mofang\Contracts\MofangGateway;
use Illuminate\Support\Facades\DB;

class MofangCatalogSync
{
    public function __construct(private readonly MofangGateway $gateway) {}

    public function sync(): int
    {
        $items = $this->gateway->fetchProducts();
        $categoryMap = app(MofangCategoryCatalog::class)->productCategories();
        $existingStatuses = UpstreamProduct::query()
            ->where('source', 'mofang')
            ->pluck('status', 'upstream_id')
            ->all();

        DB::transaction(function () use ($items, $categoryMap, $existingStatuses): void {
            foreach ($items as $item) {
                $upstreamId = trim((string) ($item['id'] ?? ''));

                if ($upstreamId === '') {
                    continue;
                }

                $name = (string) ($item['name'] ?? '未命名商品');
                $category = $categoryMap[$name] ?? null;
                $rawPayload = $item;

                if ($category !== null) {
                    $rawPayload['category_parent'] = $category['parent'];
                    $rawPayload['category_name'] = $category['name'];
                    $rawPayload['category_gid'] = $category['gid'];
                    $rawPayload['category_fid'] = $category['fid'];
                }

                $status = in_array($existingStatuses[$upstreamId] ?? null, ['imported', 'ignored'], true)
                    ? $existingStatuses[$upstreamId]
                    : 'available';

                UpstreamProduct::updateOrCreate(
                    ['source' => 'mofang', 'upstream_id' => $upstreamId],
                    [
                        'name' => $name,
                        'group_name' => $category['name'] ?? (string) ($item['group'] ?? '上游商品'),
                        'region' => (string) ($item['region'] ?? ''),
                        'description' => (string) ($item['description'] ?? ''),
                        'prices' => $this->normalizePrices($item['prices'] ?? []),
                        'currency' => 'CNY',
                        'status' => $status,
                        'raw_payload' => $rawPayload,
                        'synced_at' => now(),
                    ],
                );
            }
        });

        return count($items);
    }

    /**
     * @param  array<string, mixed>  $prices
     * @return array<string, float>
     */
    private function normalizePrices(array $prices): array
    {
        $normalized = [];

        foreach ($prices as $cycle => $price) {
            if (is_numeric($price)) {
                $normalized[(string) $cycle] = (float) $price;
            }
        }

        return $normalized;
    }
}
