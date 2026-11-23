<?php

namespace App\Services\Mofang;

use App\Models\UpstreamSupplier;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Throwable;

class MofangCategoryCatalog
{
    /**
     * @return array<int, array{parent: string, children: array<int, array{name: string, gid: string, fid: string}>}>
     */
    public function tree(): array
    {
        $supplier = UpstreamSupplier::query()->where('status', 'active')->latest('id')->first();
        if (! $supplier) {
            return [];
        }

        try {
            $html = $this->request($supplier)->get('/cart')->throw()->body();
        } catch (Throwable) {
            return [];
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        $groups = [];

        foreach ($xpath->query('//nav[contains(@class,"nq-cart-menu")]//div[contains(@class,"nq-cart-nav-group")]') as $groupNode) {
            $parentNode = $xpath->query('.//a[contains(@class,"nq-cart-nav-group-toggle")]//span', $groupNode)->item(0);
            $parent = $parentNode ? trim(preg_replace('/\s+/u', ' ', $parentNode->textContent)) : '';
            if ($parent === '') {
                continue;
            }

            $children = [];
            foreach ($xpath->query('.//a[contains(@class,"nq-cart-nav-sub-link")]', $groupNode) as $link) {
                $name = '';
                foreach ($xpath->query('.//span[not(contains(@class,"nq-cart-nav-flag")) and not(contains(@class,"nq-cart-nav-tag"))]', $link) as $span) {
                    $candidate = trim(preg_replace('/\s+/u', ' ', $span->textContent));
                    if ($candidate !== '') {
                        $name = $candidate;
                    }
                }

                $href = (string) $link->getAttribute('href');
                parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
                if ($name !== '' && isset($query['gid'])) {
                    $children[] = [
                        'name' => $name,
                        'gid' => (string) $query['gid'],
                        'fid' => (string) ($query['fid'] ?? ''),
                    ];
                }
            }

            if ($children !== []) {
                $groups[] = ['parent' => $parent, 'children' => $children];
            }
        }

        return $groups;
    }

    /**
     * @return array<string, array{parent: string, name: string, gid: string, fid: string}>
     */
    public function productCategories(): array
    {
        $map = [];
        foreach ($this->tree() as $group) {
            foreach ($group['children'] as $child) {
                $names = $this->productNames($child['fid'], $child['gid']);
                foreach ($names as $name) {
                    $map[$name] = [
                        'parent' => $group['parent'],
                        'name' => $child['name'],
                        'gid' => $child['gid'],
                        'fid' => $child['fid'],
                    ];
                }
            }
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function productNames(string $fid, string $gid): array
    {
        $supplier = UpstreamSupplier::query()->where('status', 'active')->latest('id')->first();
        if (! $supplier) {
            return [];
        }

        try {
            $html = $this->request($supplier)->get('/cart', ['fid' => $fid, 'gid' => $gid])->throw()->body();
        } catch (Throwable) {
            return [];
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        $names = [];

        foreach ($xpath->query('//*[contains(@class,"nq-product-name")]') as $node) {
            $name = trim(preg_replace('/\s+/u', ' ', $node->textContent));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function request(UpstreamSupplier $supplier)
    {
        $request = Http::withOptions(['cookies' => new CookieJar])
            ->baseUrl(rtrim($supplier->base_url, '/'))
            ->withBasicAuth($supplier->username, $supplier->api_key)
            ->withHeaders(['Accept' => 'text/html'])
            ->timeout(20);

        if (! $supplier->verify_ssl) {
            $request->withoutVerifying();
        }

        return $request;
    }
}
