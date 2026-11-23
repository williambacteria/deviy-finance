<?php

namespace App\Services\Mofang;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPrice;
use App\Models\UpstreamProduct;
use App\Models\UpstreamSupplier;
use App\Support\Billing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MofangProductImporter
{
    public function import(UpstreamProduct $upstream, float $markupPercent, ?int $productGroupId = null, ?string $productName = null): Product
    {
        abort_unless($upstream->source === 'mofang', 404);
        abort_if((float) $markupPercent < 0 || (float) $markupPercent > 500, 422, '加价比例必须在 0–500% 之间。');

        return DB::transaction(function () use ($upstream, $markupPercent, $productGroupId, $productName): Product {
            $group = $productGroupId
                ? ProductGroup::findOrFail($productGroupId)
                : ProductGroup::firstOrCreate(
                    ['slug' => 'mofang-upstream'],
                    ['name' => '魔方上游', 'description' => '通过魔方财务 API 同步并上架的商品', 'sort_order' => 9],
                );

            $supplier = UpstreamSupplier::query()->where('status', 'active')->latest('id')->first();
            $baseUrl = rtrim((string) ($supplier?->base_url ?: config('mofang.base_url')), '/');
            $upstreamPurchaseUrl = $baseUrl !== ''
                ? $baseUrl.'/cart?action=configureproduct&pid='.$upstream->upstream_id
                : '';
            $upstreamSpecs = $this->parseDescriptionSpecs((string) data_get($upstream->raw_payload, 'description_html', ''));
            $upstreamStock = (int) data_get($upstream->raw_payload, 'stock', 0);
            $upstreamConfig = app(MofangProductConfigScraper::class)->fetch($upstream);
            $upstreamGroups = $upstreamConfig['groups'];
            $upstreamOsGroups = $upstreamConfig['os_groups'];
            $upstreamCycles = $upstreamConfig['cycles'];
            $cycleDiscounts = [];
            foreach (Billing::cycles() as $cycle => $meta) {
                $cycleDiscounts[$cycle] = (float) ($upstreamCycles[$cycle]['discount'] ?? 1);
            }

            $rawCode = Str::upper(Str::slug($upstream->upstream_id, '-'));
            $code = Str::startsWith($rawCode, 'MF-') ? $rawCode : 'MF-'.$rawCode;
            $existing = Product::query()->where('code', $code)->first();

            if ($existing && (string) $existing->upstream_product_id !== (string) $upstream->upstream_id) {
                $code .= '-'.Str::upper(Str::random(4));
            }

            $product = Product::updateOrCreate(
                ['upstream_source' => 'mofang', 'upstream_product_id' => $upstream->upstream_id],
                [
                    'product_group_id' => $group->id,
                    'name' => $productName ?: $upstream->name,
                    'code' => $code,
                    'type' => \App\Support\MofangServiceType::product((string) data_get($upstream->raw_payload, 'type', ''), (string) $group->name.' '.(string) $group->parent?->name.' '.(string) $upstream->group_name, $upstream->name),
                    'region' => ! in_array(trim((string) $upstream->region), ['', 'default'], true) ? $upstream->region : (Product::query()->where('upstream_source', 'mofang')->where('upstream_product_id', $upstream->upstream_id)->value('region') ?: 'default'),
                    'short_description' => Str::limit((string) $upstream->description, 200, ''),
                    'description' => $upstream->description,
                    'stock' => 999,
                    'status' => 'active',
                    'sort_order' => 0,
                    'metadata' => [
                        'upstream' => 'mofang',
                        'upstream_id' => $upstream->upstream_id,
                        'upstream_purchase_url' => $upstreamPurchaseUrl,
                        'upstream_specs' => $upstreamSpecs,
                        'upstream_config_groups' => $upstreamGroups,
                        'upstream_os_groups' => $upstreamOsGroups,
                        'cycle_discounts' => $cycleDiscounts,
                        'upstream_stock' => $upstreamStock > 0 ? $upstreamStock : null,
                        'markup_percent' => $markupPercent,
                    ],
                ],
            );

            $multiplier = bcadd('1', bcdiv((string) $markupPercent, '100', 6), 6);
            $monthlyCost = $upstream->prices['monthly'] ?? null;
            $monthlyLocal = is_numeric($monthlyCost) ? bcmul((string) $monthlyCost, $multiplier, 6) : null;

            foreach (Billing::cycles() as $cycle => $meta) {
                $cost = $upstream->prices[$cycle] ?? null;
                $localPrice = null;

                if (is_numeric($cost)) {
                    $localPrice = bcmul((string) $cost, $multiplier, 6);
                } elseif ($monthlyLocal !== null && isset($upstreamCycles[$cycle])) {
                    $localPrice = bcmul(
                        $monthlyLocal,
                        (string) ((int) $meta['months'] * (float) $cycleDiscounts[$cycle]),
                        6,
                    );
                }

                if ($localPrice === null) {
                    continue;
                }

                ProductPrice::updateOrCreate(
                    ['product_id' => $product->id, 'billing_cycle' => $cycle],
                    [
                        'price' => $localPrice,
                        'cost_price' => is_numeric($cost) ? $cost : $monthlyCost,
                        'setup_fee' => 0,
                        'is_active' => true,
                    ],
                );
            }

            $upstream->update([
                'status' => 'imported',
                'markup_percent' => $markupPercent,
                'mapped_product_id' => $product->id,
            ]);

            return $product;
        });
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function parseDescriptionSpecs(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all(
            '/class="config-label"[^>]*>(.*?)<\/div>.*?class="config-value"[^>]*>(.*?)<\/div>/is',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $specs = [];
        foreach ($matches as $match) {
            $label = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
            $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))));

            if ($label !== '' && $value !== '') {
                $specs[] = ['label' => $label, 'value' => $value];
            }
        }

        return $specs;
    }
}
