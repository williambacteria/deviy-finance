<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;

final class MofangServiceType
{
    public static function product(string $type, string $group, string $name): string
    {
        $type = strtolower($type);
        if ($type === 'cdn' || preg_match('/CDN|加速|普防型|高防型/u', $group.' '.$name)) {
            return 'cdn';
        }
        if (in_array($type, ['dcim', 'server', 'baremetal', 'bare_metal'], true) || preg_match('/裸金属|物理机|物理服务器/u', $group)) {
            return 'bare_metal';
        }

        return str_contains(strtoupper($group), 'NAT') ? 'nat_vm' : 'cloud_vm';
    }

    public static function forService(Service $service, array $detail = []): string
    {
        $upstreamType = (string) data_get($detail, 'data.host_data.type', '');
        if ($upstreamType !== '') {
            return self::product($upstreamType, '', '');
        }

        return (string) data_get($service->specs, 'upstream.type', $service->product?->type ?? 'cloud_vm');
    }
}
