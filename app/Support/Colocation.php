<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;

final class Colocation
{
    public static function product(?Product $product): bool
    {
        return $product?->type === 'colocation';
    }

    public static function service(Service $service): bool
    {
        return self::product($service->product);
    }

    public static function formFactor(Service $service): string
    {
        $formFactor = data_get($service->specs, 'colocation.form_factor');
        $rackUnits = data_get($service->specs, 'colocation.rack_units')
            ?? data_get($service->product?->metadata, 'colocation.rack_units');

        return $formFactor ?: ($rackUnits ? $rackUnits.'U' : '按设备登记');
    }

    public static function memory(Service $service): string
    {
        $memory = data_get($service->specs, 'memory_gb');

        return $memory ? trim($memory.' GB '.data_get($service->specs, 'memory_type', '')) : '按设备登记';
    }

    public static function bandwidth(Service $service, string $direction): string
    {
        $mbps = data_get($service->specs, $direction.'_mbps');
        if ($mbps === null) {
            return '按交付配置';
        }

        return $mbps >= 1000 ? ($mbps / 1000).' Gbps' : $mbps.' Mbps';
    }

    public static function monthlyRenewal(Service $service): ?string
    {
        $amount = data_get($service->specs, 'contract_prices.monthly');

        return is_numeric($amount) ? Money::format($amount).'/月' : null;
    }

    /** @return array<string, string|null> */
    public static function fields(Service $service): array
    {
        $specs = (array) $service->specs;
        $fields = [
            '机位规格' => self::formFactor($service),
            'CPU 型号' => $specs['cpu_model'] ?? '客户自有设备',
            '内存' => self::memory($service),
        ];
        if (! empty($specs['storage_description'])) {
            $fields['硬盘'] = $specs['storage_description'];
        }

        return $fields + [
            '显卡' => $specs['gpu_model'] ?? '按设备登记',
            '托管机房' => data_get($specs, 'colocation.datacenter', $service->region),
            '上行带宽' => self::bandwidth($service, 'upload'),
            '下行带宽' => self::bandwidth($service, 'download'),
            '公网 IP' => $service->ip_address ?: '未登记',
        ];
    }
}
