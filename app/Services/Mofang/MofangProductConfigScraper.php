<?php

namespace App\Services\Mofang;

use App\Models\UpstreamProduct;
use App\Models\UpstreamSupplier;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Throwable;

class MofangProductConfigScraper
{
    /**
     * @return array{groups: array<int, array{id: string, label: string, options: array<int, array{value: string, label: string}>}>, os_groups: array<int, array{family: string, versions: array<int, array{value: string, label: string}>}>, cycles: array<string, array{label: string, discount: float}>}
     */
    public function fetch(UpstreamProduct $upstream): array
    {
        $supplier = UpstreamSupplier::query()->where('status', 'active')->latest('id')->first();
        if (! $supplier) {
            return ['groups' => [], 'os_groups' => [], 'cycles' => []];
        }

        try {
            $jar = new CookieJar;
            $request = Http::withOptions(['cookies' => $jar])
                ->baseUrl(rtrim($supplier->base_url, '/'))
                ->withBasicAuth($supplier->username, $supplier->api_key)
                ->withHeaders(['Accept' => 'text/html'])
                ->timeout(20);

            if (! $supplier->verify_ssl) {
                $request->withoutVerifying();
            }

            $html = $request->get('/cart', [
                'action' => 'configureproduct',
                'pid' => $upstream->upstream_id,
            ])->throw()->body();
        } catch (Throwable) {
            return ['groups' => [], 'os_groups' => [], 'cycles' => []];
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);

        $osGroups = [];
        foreach ($xpath->query('//div[contains(@class, "nq-productconfig-os-card")]') as $card) {
            $familyNode = $xpath->query('.//*[contains(@class, "nq-productconfig-os-name")]', $card)->item(0);
            $family = $familyNode ? trim($familyNode->textContent) : '';
            $iconNode = $xpath->query('.//*[contains(@class, "nq-productconfig-os-icon")]', $card)->item(0);
            $icon = $iconNode ? trim((string) $iconNode->getAttribute('src')) : '';
            if ($icon !== '' && ! preg_match('#^https?://#i', $icon)) {
                $icon = rtrim($supplier->base_url, '/').'/'.ltrim($icon, '/');
            }
            $versions = [];

            foreach ($xpath->query('.//*[contains(@class, "nq-productconfig-os-option")]', $card) as $option) {
                $value = trim((string) $option->getAttribute('data-value'));
                $label = trim(preg_replace('/\s+/', ' ', $option->textContent));
                if ($value !== '' && $label !== '') {
                    $versions[] = ['value' => $value, 'label' => $label];
                }
            }

            if ($family !== '' && $versions !== []) {
                $osGroups[] = ['family' => $family, 'icon' => $icon, 'versions' => $versions];
            }
        }

        $cycles = [];
        foreach ($xpath->query('//label[contains(@class, "nq-productconfig-cycle")]') as $label) {
            $input = $xpath->query('.//input[@name="billingcycle"]', $label)->item(0);
            if (! $input) {
                continue;
            }

            $cycle = trim((string) $input->getAttribute('value'));
            if ($cycle === '') {
                continue;
            }

            $discountNode = $xpath->query('.//*[contains(@class, "nq-productconfig-discount")]', $label)->item(0);
            $discount = 1.0;
            if ($discountNode && preg_match('/([0-9.]+)\s*折/u', $discountNode->textContent, $matches)) {
                $discount = min(1, max(0.1, (float) $matches[1] / 10));
            }

            $labelText = trim((string) $label->textContent);
            if ($discountNode) {
                $labelText = trim(str_replace(trim($discountNode->textContent), '', $labelText));
            }

            $cycles[$cycle] = [
                'label' => $labelText,
                'discount' => $discount,
            ];
        }

        $groups = [];
        $seenGroups = [];
        foreach ($xpath->query('//div[contains(@class, "nq-productconfig-row")]') as $row) {
            $labelNode = $xpath->query('.//label[contains(@class, "nq-productconfig-label")]', $row)->item(0);
            $label = $labelNode ? trim(preg_replace('/\s+/', ' ', $labelNode->textContent)) : '';
            if ($label === '' || in_array($label, ['周期', '主机名', '主机密码'], true)) {
                continue;
            }

            $configId = '';
            $options = [];
            $osCard = $xpath->query('.//*[contains(@class, "nq-productconfig-os-card")]', $row)->item(0);

            if ($osCard) {
                $hiddenInput = $xpath->query('.//input[starts-with(@name, "configoption[")]', $row)->item(0);
                if ($hiddenInput && preg_match('/configoption\[(\d+)\]/', (string) $hiddenInput->getAttribute('name'), $matches)) {
                    $configId = $matches[1];
                }

                foreach ($xpath->query('.//*[contains(@class, "nq-productconfig-os-card")]', $row) as $card) {
                    $familyNode = $xpath->query('.//*[contains(@class, "nq-productconfig-os-name")]', $card)->item(0);
                    $family = $familyNode ? trim($familyNode->textContent) : '';
                    foreach ($xpath->query('.//*[contains(@class, "nq-productconfig-os-option")]', $card) as $option) {
                        $value = trim((string) $option->getAttribute('data-value'));
                        $version = trim(preg_replace('/\s+/', ' ', $option->textContent));
                        if ($value !== '' && $version !== '') {
                            $options[] = ['value' => $value, 'label' => trim($family.' - '.$version)];
                        }
                    }
                }
            } else {
                foreach ($xpath->query('.//input[starts-with(@name, "configoption[")]', $row) as $input) {
                    if (preg_match('/configoption\[(\d+)\]/', (string) $input->getAttribute('name'), $matches)) {
                        $configId = $configId ?: $matches[1];
                    }
                    $value = trim((string) $input->getAttribute('value'));
                    $optionLabel = $input->parentNode ? trim(preg_replace('/\s+/', ' ', $input->parentNode->textContent)) : '';
                    if ($value !== '' && $optionLabel !== '' && ! in_array($value, array_column($options, 'value'), true)) {
                        $options[] = ['value' => $value, 'label' => $optionLabel];
                    }
                }

                foreach ($xpath->query('.//select[starts-with(@name, "configoption[")]', $row) as $select) {
                    if (preg_match('/configoption\[(\d+)\]/', (string) $select->getAttribute('name'), $matches)) {
                        $configId = $configId ?: $matches[1];
                    }
                    foreach ($xpath->query('.//option', $select) as $option) {
                        $value = trim((string) $option->getAttribute('value'));
                        $optionLabel = trim(preg_replace('/\s+/', ' ', $option->textContent));
                        if ($value !== '' && $optionLabel !== '' && ! in_array($value, array_column($options, 'value'), true)) {
                            $options[] = ['value' => $value, 'label' => $optionLabel];
                        }
                    }
                }
            }

            if ($configId !== '' && $options !== [] && ! isset($seenGroups[$configId])) {
                $seenGroups[$configId] = true;
                $groups[] = ['id' => $configId, 'label' => $label, 'options' => $options];
            }
        }

        $groups = $this->applyOptionPrices($request, $html, (string) $upstream->upstream_id, $groups);

        return ['groups' => $groups, 'os_groups' => $osGroups, 'cycles' => $cycles];
    }

    /**
     * @param  array<int, array{id: string, label: string, options: array<int, array{value: string, label: string}>}>  $groups
     * @return array<int, array{id: string, label: string, options: array<int, array{value: string, label: string, price?: float}>}>
     */
    private function applyOptionPrices($request, string $html, string $productId, array $groups): array
    {
        if (! preg_match('/name="token"\s+value="([^"]+)"/i', $html, $tokenMatch)) {
            return $groups;
        }

        $defaults = [];
        foreach ($groups as $group) {
            if (! empty($group['options'][0]['value'])) {
                $defaults['configoption['.$group['id'].']'] = $group['options'][0]['value'];
            }
        }

        $baseTotal = $this->orderTotal($request, $tokenMatch[1], $productId, $defaults);
        if ($baseTotal === null) {
            return $groups;
        }

        foreach ($groups as $groupIndex => $group) {
            foreach ($group['options'] as $optionIndex => $option) {
                $config = $defaults;
                $config['configoption['.$group['id'].']'] = $option['value'];
                $total = $this->orderTotal($request, $tokenMatch[1], $productId, $config);

                if ($total !== null) {
                    $groups[$groupIndex]['options'][$optionIndex]['price'] = round(max(0, $total - $baseTotal), 2);
                }
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, string>  $config
     */
    private function orderTotal($request, string $token, string $productId, array $config): ?float
    {
        try {
            $response = $request->asForm()->post('/cart?action=ordersummary', array_merge([
                'token' => $token,
                'pid' => $productId,
                'currencyid' => '1',
                'qty' => '1',
                'billingcycle' => 'monthly',
            ], $config))->throw()->body();
        } catch (Throwable) {
            return null;
        }

        $text = html_entity_decode(strip_tags($response), ENT_QUOTES, 'UTF-8');
        if (preg_match('/总计[\s\S]{0,300}?(?:￥|¥)\s*([0-9]+(?:\.[0-9]+)?)/u', $text, $matches)) {
            return (float) $matches[1];
        }
        if (preg_match('/(?:￥|¥)\s*([0-9]+(?:\.[0-9]+)?)/u', $text, $matches)) {
            return (float) $matches[1];
        }
        return null;
    }
}
