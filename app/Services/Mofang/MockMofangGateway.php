<?php

namespace App\Services\Mofang;

use App\Services\Mofang\Contracts\MofangGateway;

class MockMofangGateway implements MofangGateway
{
    public function fetchProducts(): array
    {
        return [
            [
                'id' => 'MF-HK-CN2-2C4G',
                'name' => '香港 CN2 · 2C4G',
                'group' => '云服务器',
                'region' => '香港',
                'description' => '上游演示商品：2 核 4G / 40G SSD / 10Mbps',
                'prices' => ['monthly' => 88, 'quarterly' => 248, 'annually' => 888],
            ],
            [
                'id' => 'MF-JP-NAT-1C1G',
                'name' => '日本 NAT · 1C1G',
                'group' => 'NAT 云机',
                'region' => '日本',
                'description' => '上游演示商品：1 核 1G / 20G SSD / 20 个端口',
                'prices' => ['monthly' => 25, 'quarterly' => 69, 'annually' => 249],
            ],
            [
                'id' => 'MF-US-HIGH-DEFENSE',
                'name' => '美国高防 · 4C8G',
                'group' => '高防云服务器',
                'region' => '美国',
                'description' => '上游演示商品：4 核 8G / 100G SSD / 100Gbps 高防',
                'prices' => ['monthly' => 268, 'quarterly' => 768, 'annually' => 2680],
            ],
        ];
    }

    public function createOrder(array $payload): array
    {
        return [
            'ok' => true,
            'order_id' => 'MOCK-ORDER-'.now()->format('YmdHis'),
            'status' => 'pending',
            'payload' => $payload,
        ];
    }

    public function fetchService(string $upstreamServiceId): array
    {
        return [
            'ok' => true,
            'service_id' => $upstreamServiceId,
            'status' => 'active',
            'ip' => '198.51.100.88',
        ];
    }

    public function payOrder(string $upstreamOrderId): array
    {
        return [
            'ok' => true,
            'order_id' => $upstreamOrderId,
            'service_id' => 'MOCK-SVC-'.strtoupper(substr(md5($upstreamOrderId), 0, 10)),
            'status' => 'paid',
        ];
    }

    public function findServiceId(string $invoiceId, string $email = '', string $productId = ''): ?string
    {
        return null;
    }

    public function renewService(string $upstreamServiceId, array $payload): array
    {
        return [
            'ok' => true,
            'service_id' => $upstreamServiceId,
            'status' => 'active',
            'expire_at' => now()->addMonths((int) ($payload['months'] ?? 1))->toDateString(),
        ];
    }

    public function fetchServiceMetrics(string $upstreamServiceId): array
    {
        return [
            'cpu' => [18, 22, 20, 25, 23, 28, 26, 30, 29, 32, 30, 35],
            'memory' => [42, 44, 43, 46, 48, 47, 49, 51, 50, 52, 51, 53],
            'disk' => [35, 35, 36, 36, 37, 37, 38, 38, 38, 39, 39, 40],
            'bandwidth' => [2.4, 3.1, 2.8, 4.2, 3.6, 5.1, 4.4, 5.8, 5.2, 6.4, 5.7, 6.8],
        ];
    }

    public function fetchTraffic(string $upstreamServiceId): array
    {
        return ['data' => ['bwusage' => 0, 'bwlimit' => 2000, 'dcim' => ['flowpacket' => []]]];
    }

    public function buyTrafficPackage(string $upstreamServiceId, array $payload): array
    {
        return ['status' => 200, 'data' => ['invoiceid' => 'MOCK-FLOW-INVOICE']];
    }

    public function performServiceAction(string $upstreamServiceId, string $action, array $payload = []): array
    {
        return [
            'ok' => true,
            'service_id' => $upstreamServiceId,
            'action' => $action,
            'status' => $action === 'stop' ? 'stopped' : 'active',
        ];
    }

    public function fetchVnc(string $upstreamServiceId): array
    {
        return [
            'status' => 200,
            'data' => ['url' => 'https://example.com/vnc?host='.$upstreamServiceId],
        ];
    }
}
