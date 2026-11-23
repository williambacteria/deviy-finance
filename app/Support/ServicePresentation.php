<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;

final class ServicePresentation
{
    public static function kind(?Product $product): string
    {
        if (! $product) {
            return 'cloud_vm';
        }
        if ($product->type === 'cdn') {
            return 'cdn';
        }
        $group = $product->group;
        for ($level = 0; $group && $level < 4; $level++) {
            if ($group->slug === 'cdn' || str_contains($group->name, '内容分发网络')) {
                return 'cdn';
            }
            $group = $group->parent;
        }

        return $product->type;
    }

    /** @return array<string, string> */
    public static function cdnFields(?Product $product, array $configuration = []): array
    {
        $fields = [];
        foreach (array_merge((array) data_get($product?->metadata, 'upstream_specs', []), $configuration) as $row) {
            $label = trim(strip_tags((string) ($row['label'] ?? '')));
            $value = trim(strip_tags((string) ($row['value'] ?? '')));
            if ($label !== '' && $value !== '' && ! preg_match('/CPU|内存|系统盘|数据盘|硬盘|操作系统|公网.?IP|带宽/i', $label)) {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }

    /** @return array{upload: ?float, download: ?float, source: string, text: string} */
    public static function bandwidth(?Product $product, array $specs): array
    {
        $upload = null;
        $download = null;
        $source = '';
        $rows = array_merge((array) data_get($specs, 'upstream.configuration', []), (array) data_get($product?->metadata, 'upstream_specs', []));
        foreach ($rows as $index => $row) {
            $label = (string) ($row['label'] ?? '');
            $value = trim((string) ($row['value'] ?? ''));
            if (! str_contains($label, '带宽')) {
                continue;
            }
            $candidateUpload = null;
            $candidateDownload = null;
            if (preg_match('/(?:上行|上传)[：:\s]*(\d+(?:\.\d+)?)\s*(G|M)?(?:bps)?/iu', $value, $match)
                || preg_match('/(\d+(?:\.\d+)?)\s*(G|M)(?:bps)?\s*上/iu', $value, $match)) {
                $candidateUpload = (float) $match[1] * (strtoupper($match[2] ?? '') === 'G' ? 1000 : 1);
            }
            if (preg_match('/(?:下行|下载)[：:\s]*(\d+(?:\.\d+)?)\s*(G|M)?(?:bps)?/iu', $value, $match)
                || preg_match('/(\d+(?:\.\d+)?)\s*(G|M)(?:bps)?\s*下/iu', $value, $match)) {
                $candidateDownload = (float) $match[1] * (strtoupper($match[2] ?? '') === 'G' ? 1000 : 1);
            }
            if ($candidateUpload === null && $candidateDownload === null && preg_match('/(\d+(?:\.\d+)?)\s*(G|M)(?:bps)?/iu', $value, $match)) {
                $amount = (float) $match[1] * (strtoupper($match[2]) === 'G' ? 1000 : 1);
                if (str_contains($label, '下')) {
                    $candidateDownload = $amount;
                } else {
                    $candidateUpload = $amount;
                }
            }
            if ($candidateUpload !== null && $candidateUpload > 0 && $upload === null) {
                $upload = $candidateUpload;
                $source = $index < count((array) data_get($specs, 'upstream.configuration', [])) ? '实例配置' : ($source ?: '套餐配置');
            }
            if ($candidateDownload !== null && $candidateDownload > 0 && $download === null) {
                $download = $candidateDownload;
                $source = $source ?: '套餐配置';
            }
        }
        $bare = self::kind($product) === 'bare_metal';
        if (! $bare || data_get($specs, 'bandwidth_source')) {
            $upload ??= is_numeric($specs['upload_mbps'] ?? null) && (float) $specs['upload_mbps'] > 0 ? (float) $specs['upload_mbps'] : null;
            $download ??= is_numeric($specs['download_mbps'] ?? null) && (float) $specs['download_mbps'] > 0 ? (float) $specs['download_mbps'] : null;
        }
        $display = fn (?float $value): string => $value === null ? '待同步' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').' Mbps';

        return ['upload' => $upload, 'download' => $download, 'source' => $source ?: ($bare ? '待同步' : '实例配置'), 'text' => '上行 '.$display($upload).' / 下行 '.$display($download)];
    }

    /** @return array<string, mixed> */
    public static function service(Service $service): array
    {
        $product = $service->product;
        $specs = (array) $service->specs;
        $kind = self::kind($product);
        if ($kind === 'colocation') {
            $summary = [Colocation::formFactor($service), $specs['cpu_model'] ?? '客户自有设备', Colocation::memory($service)];
            if (! empty($specs['gpu_model'])) {
                $summary[] = $specs['gpu_model'];
            }

            return [
                'kind' => $kind,
                'is_cdn' => false,
                'family' => '服务器托管',
                'location' => data_get($specs, 'colocation.datacenter', $service->region),
                'title' => $service->name,
                'summary' => implode(' · ', $summary),
                'network' => $service->ip_address ?: '未登记',
                'network_label' => '公网 IP',
                'network_note' => '客户自有设备 · 人工托管',
                'capacity' => '上行 '.Colocation::bandwidth($service, 'upload').' / 下行 '.Colocation::bandwidth($service, 'download'),
                'capacity_label' => '上下行带宽',
                'fields' => Colocation::fields($service),
                'reboot' => false,
            ];
        }
        if ($kind === 'cdn') {
            $fields = self::cdnFields($product, (array) data_get($specs, 'upstream.configuration', []));
            $nodes = $fields['节点数量'] ?? $fields['套餐'] ?? '按套餐配置';
            $domains = $fields['域名'] ?? '按套餐配置';
            $traffic = $fields['流量'] ?? '按套餐配置';

            return ['kind' => $kind, 'is_cdn' => true, 'family' => 'CDN 加速', 'location' => 'CDN 加速服务', 'title' => $product?->name ?: $service->name, 'summary' => '节点：'.$nodes.' · 域名：'.$domains, 'network' => 'CDN 专用面板', 'network_label' => '接入方式', 'network_note' => '通过域名接入', 'capacity' => $traffic, 'capacity_label' => '套餐流量', 'fields' => $fields, 'reboot' => false];
        }
        $bandwidth = self::bandwidth($product, $specs);
        $nat=$kind==='nat_vm'||$service->nat_ip;
        $address = $nat ? (RemoteEndpoint::forService($service)['display'] ?: '公网映射待就绪') : ($service->ip_address ?: '待同步');
        $cpu = isset($specs['cpu']) ? $specs['cpu'].' 核' : 'CPU待同步';
        $memory = isset($specs['memory_gb']) ? $specs['memory_gb'].' GB 内存' : '内存待同步';
        $disk = isset($specs['disk_gb']) ? $specs['disk_gb'].' GB 磁盘' : '系统盘容量待同步';

        return ['kind' => $kind, 'is_cdn' => false, 'family' => $kind === 'bare_metal' ? '裸金属服务器' : ($kind === 'game_server' ? '游戏服务器' : '云服务器'), 'location' => $service->region && $service->region !== 'default' ? $service->region : ($kind === 'bare_metal' ? '独立 IP · 裸金属' : '独立 IP'), 'title' => $service->name, 'summary' => $cpu.' / '.$memory.' / '.$disk, 'network' => $address, 'network_label' => 'IP 地址', 'network_note' => $service->nat_ip ? 'NAT · 内网 '.($service->ip_address ?: '待同步') : '独立 IP', 'capacity' => $bandwidth['text'], 'capacity_label' => '带宽', 'bandwidth' => $bandwidth, 'fields' => [], 'reboot' => $service->status === 'active'];
    }
}
