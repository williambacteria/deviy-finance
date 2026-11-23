<?php

namespace App\Services\Mofang;

use App\Services\Mofang\Contracts\MofangGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpMofangGateway implements MofangGateway
{
    /**
     * @param  array<string, string>  $endpoints
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly int $timeout,
        private readonly array $endpoints,
        private readonly bool $verifySsl = true,
        private readonly ?string $password = null,
    ) {}

    public function fetchProducts(): array
    {
        $payload = $this->request()->get($this->endpoints['products'])->throw()->json();
        $items = data_get($payload, 'data.list')
            ?? data_get($payload, 'data.items')
            ?? data_get($payload, 'data')
            ?? $payload['items']
            ?? [];

        if (! is_array($items)) {
            throw new RuntimeException('魔方上游返回的商品结构无法识别，请检查接口版本。');
        }

        $mapped = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $cycle = (string) ($item['billingcycle'] ?? '');
            if ($cycle === '') {
                $cycle = match ((string) ($item['cycle'] ?? '')) {
                    '月' => 'monthly',
                    '季' => 'quarterly',
                    '半年' => 'semiannually',
                    '年' => 'annually',
                    default => 'monthly',
                };
            }

            $prices = [];
            if (is_numeric($item['price'] ?? null)) {
                $prices[$cycle] = (float) $item['price'];
            } elseif (is_array($item['prices'] ?? null)) {
                $prices = $item['prices'];
            }

            $description = html_entity_decode((string) ($item['description'] ?? $item['notes'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $descriptionHtml = $description;
            $description = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $description) ?? $description;
            $description = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $description) ?? $description;
            $description = trim((string) preg_replace('/\s+/u', ' ', strip_tags($description)));
            $name = (string) ($item['name'] ?? $item['product_name'] ?? '未命名商品');
            $rawGroup = (string) ($item['group_name'] ?? $item['group'] ?? $item['type'] ?? '');

            $mapped[] = [
                'id' => (string) ($item['id'] ?? $item['product_id'] ?? ''),
                'name' => $name,
                'group' => $this->category($name, $rawGroup),
                'type' => (string) ($item['type'] ?? ''),
                'region' => (string) ($item['region'] ?? $item['area'] ?? ''),
                'description' => mb_substr($description, 0, 5000),
                'description_html' => mb_substr($descriptionHtml, 0, 100000),
                'stock' => (int) ($item['qty'] ?? 0),
                'prices' => $prices,
            ];
        }

        return array_values(array_filter($mapped, fn (array $item): bool => $item['id'] !== ''));
    }

    public function createOrder(array $payload): array
    {
        return $this->resourceClient()->createOrder($payload);
    }

    public function payOrder(string $upstreamOrderId): array
    {
        return $this->resourceClient()->payOrder($upstreamOrderId);
    }

    public function findServiceId(string $invoiceId, string $email = '', string $productId = ''): ?string
    {
        return $this->resourceClient()->findServiceId($invoiceId, $email, $productId);
    }

    public function renewService(string $upstreamServiceId, array $payload): array
    {
        return $this->resourceClient()->renewService($upstreamServiceId,$payload);
    }

    public function fetchService(string $upstreamServiceId): array
    {
        return $this->resourceClient()->fetchService($upstreamServiceId);
    }

    public function fetchServiceMetrics(string $upstreamServiceId): array
    {
        return $this->resourceClient()->fetchMetrics($upstreamServiceId);
    }

    public function fetchTraffic(string $upstreamServiceId): array
    {
        return $this->resourceClient()->fetchTraffic($upstreamServiceId);
    }

    public function buyTrafficPackage(string $upstreamServiceId, array $payload): array
    {
        return $this->resourceClient()->buyTrafficPackage($upstreamServiceId, $payload);
    }

    public function fetchVnc(string $upstreamServiceId): array
    {
        return $this->resourceClient()->fetchVnc($upstreamServiceId);
    }

    public function performServiceAction(string $upstreamServiceId, string $action, array $payload = []): array
    {
        return $this->resourceClient()->performAction($upstreamServiceId, $action, $payload);
    }

    private function category(string $name, string $rawGroup): string
    {
        $knownTypes = [
            'cloud' => '云服务器',
            'dcimcloud' => '魔方云',
            'server' => '物理服务器',
            'cdn' => 'CDN',
            'dcim' => 'DCIM',
            'hostingaccount' => '虚拟主机',
            'other' => '其他',
        ];

        if ($rawGroup !== '' && ! isset($knownTypes[$rawGroup])) {
            return $rawGroup;
        }

        $prefix = trim((string) preg_split('/[-—]/u', $name, 2)[0]);
        if ($prefix !== '' && $prefix !== $name && ! preg_match('/^[\d.]+$/', $prefix)) {
            return $prefix;
        }

        return $knownTypes[$rawGroup] ?? '其他';
    }

    private function request(): PendingRequest
    {
        if ($this->baseUrl === '' || $this->username === '' || $this->apiKey === '') {
            throw new RuntimeException('魔方上游未配置完整：MOFANG_BASE_URL / MOFANG_USERNAME / MOFANG_API_KEY。');
        }

        $request = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withBasicAuth($this->username, $this->apiKey)
            ->acceptJson()
            ->connectTimeout(min(5, $this->timeout))
            ->timeout($this->timeout);

        if (! $this->verifySsl) {
            $request->withoutVerifying();
        }

        return $request;
    }

    private function resourceClient(): MofangResourceApiClient
    {
        return new MofangResourceApiClient(
            $this->baseUrl,
            $this->username,
            $this->apiKey,
            $this->timeout,
            $this->endpoints,
            $this->verifySsl,
        );
    }
}
