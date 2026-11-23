<?php

namespace App\Services\Mofang;

use App\Models\Service;
use App\Support\MofangServiceType;
use App\Support\RemoteEndpoint;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MofangServiceSync
{
    /** @return array<int, array{label: string, value: string}> */
    public function sync(Service $service, array $detail): array
    {
        $host = (array) data_get($detail, 'data.host_data', []);
        if ($host === [] || 'mofang:'.(string) ($host['id'] ?? '') !== $service->external_ref) {
            throw new RuntimeException('魔方实例详情与本地绑定不匹配，未更新。');
        }
        $type = MofangServiceType::forService($service, $detail);
        $config = [];
        foreach ((array) data_get($detail, 'data.config_options', []) as $option) {
            $label = trim(strip_tags((string) ($option['name'] ?? '')));
            $value = trim(strip_tags((string) ($option['sub_name'] ?? '')));
            if ($label !== '' && $value !== '') {
                $config[] = ['label' => $label, 'value' => $value];
            }
        }
        DB::transaction(function () use ($service, $detail, $host, $type, $config): void {
            $current = Service::query()->lockForUpdate()->findOrFail($service->id);
            $specs = (array) $current->specs;
            $specs['upstream'] = array_merge((array) ($specs['upstream'] ?? []), ['service_id' => (string) $host['id'], 'type' => $type, 'configuration' => $config, 'capabilities' => (array) data_get($detail, 'data.dcim.auth', []), 'modules' => array_values(array_filter((array) data_get($detail, 'data.module_client_area', []), 'is_array')), 'allow_upgrade_config' => (bool) ($host['allow_upgrade_config'] ?? false), 'synced_at' => now()->toIso8601String()]);
            $updates = [];
            if ($type === 'cdn') {
                foreach (['cpu', 'memory_gb', 'disk_gb', 'data_disk_gb', 'ostype', 'nat_ports', 'internal_ip', 'remote', 'network_ips', 'upload_mbps', 'download_mbps', 'bandwidth_mbps', 'bandwidth_source'] as $key) {
                    unset($specs[$key]);
                }
                $specs['cdn'] = array_merge((array) ($specs['cdn'] ?? []), ['account' => (string) ($host['username'] ?? ''), 'password' => html_entity_decode((string) ($host['password'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
                $updates = ['os' => null, 'ip_address' => null, 'nat_ip' => null, 'nat_port_start' => null, 'nat_port_end' => null, 'bandwidth_mbps' => 0];
            } else {
                foreach ($config as $item) {
                    $label = $item['label'];
                    $value = $item['value'];
                    if (in_array(strtoupper($label), ['CPU', 'VCPU'], true)) {
                        $specs['cpu_model'] = $value;
                        if (preg_match('/\((\d+)C\)|(?<!\d)(\d+)\s*(?:核心|核)/iu', $value, $match)) {
                            $specs['cpu'] = (int) ($match[1] ?: $match[2]);
                        } elseif (ctype_digit($value)) {
                            $specs['cpu'] = (int) $value;
                        }
                    } elseif (str_contains($label, '内存') && preg_match('/(\d+)\s*G/i', $value, $match)) {
                        $specs['memory_gb'] = (int) $match[1];
                    } elseif (preg_match('/硬盘|系统盘/u', $label) && preg_match('/(\d+)\s*G/i', $value, $match)) {
                        $specs['disk_gb'] = (int) $match[1];
                    } elseif (str_contains($label, '带宽') && preg_match('/(\d+)\s*(?:M|Mbps)/i', $value, $match)) {
                        $specs['upload_mbps'] = (int) $match[1];
                        $specs['bandwidth_mbps'] = (int) $match[1];
                    }
                }
                $ips = [];
                foreach (array_merge([(string) ($host['dedicatedip'] ?? '')], (array) ($host['assignedips'] ?? [])) as $ip) {
                    if (is_string($ip) && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                        $ips[] = trim($ip);
                    }
                }
                $bandwidth = \App\Support\ServicePresentation::bandwidth($current->product, $specs);
                if ($type === 'bare_metal') {
                    $specs['upload_mbps'] = $bandwidth['upload'];
                    $specs['download_mbps'] = $bandwidth['download'];
                    $specs['bandwidth_mbps'] = $bandwidth['upload'];
                    $specs['bandwidth_source'] = $bandwidth['source'];
                }
                $specs['network_ips'] = array_values(array_unique($ips));
                $os = (string) ($host['os'] ?? $current->os);
                $remote = (array) ($specs['remote'] ?? []);
                $endpoint = RemoteEndpoint::parse($ips[0] ?? '', (int) ($host['port'] ?? 0), str_contains(strtolower($os), 'windows') ? 3389 : 22);
                $remote = array_merge($remote, ['address' => $endpoint['host'], 'port' => $endpoint['port'], 'username' => (string) ($host['username'] ?? ''), 'protocol' => str_contains(strtolower($os), 'windows') ? 'rdp' : 'ssh']);
                if (! empty($host['password']) && (int) ($remote['password_updated_at'] ?? 0) < now()->subMinutes(5)->timestamp) {
                    $remote['password'] = html_entity_decode((string) $host['password'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $specs['remote'] = $remote;
                $updates = ['os' => $os, 'ip_address' => $ips[0] ?? null, 'nat_ip' => null, 'nat_port_start' => null, 'nat_port_end' => null];
            }
            if ($type === 'bare_metal') {
                $updates['bandwidth_mbps'] = (int) ($specs['upload_mbps'] ?? 0);
            }
            $expires=\App\Support\UpstreamExpiry::from($detail);
            if($expires){$updates['expires_at']=$expires;$updates['next_due_at']=$expires->copy()->subDays(7);}
            $updates['specs'] = $specs;
            $current->update($updates);
            $product = $current->product;
            if ($product && $product->upstream_source === 'mofang' && $product->type !== $type) {
                $product->update(['type' => $type]);
            }
        });
        $service->refresh()->load('product');

        return $config;
    }
}
