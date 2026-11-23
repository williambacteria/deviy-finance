<?php

namespace App\Services\Qzvps;

use App\Models\NatMapping;
use App\Models\Service;
use App\Support\RemoteEndpoint;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QzvpsServiceSync
{
    public function __construct(private QzvpsClient $client) {}

    /** @return array<string, mixed> */
    public function sync(Service $service, ?array $info = null, ?array $rules = null): array
    {
        if (! preg_match('/^qzvps:(\d+)$/D', (string) $service->external_ref, $match)) {
            throw new RuntimeException('实例尚未绑定有效的轻舟主机 ID。');
        }
        $hostId = (int) $match[1];
        $info ??= (array) ($this->client->hostInfo($hostId)['data'] ?? []);
        if ($info === [] || (isset($info['id']) && (int) $info['id'] !== $hostId)) {
            throw new RuntimeException('轻舟实例详情为空或实例 ID 不匹配。');
        }
        $isNat = (int) ($info['is_nat'] ?? 0) === 1;
        $rules ??= $isNat ? $this->client->natAclList($hostId) : [];
        $os = (string) ($info['os_name'] ?? $service->os);
        $windows = str_contains(strtolower($os), 'windows');
        $defaultPort = $windows ? 3389 : 22;
        $remote = RemoteEndpoint::parse((string) ($info['remote_ip'] ?? ''), null, $defaultPort);
        $internalIp = trim((string) ($info['ip'] ?? ''));
        $normalized = $this->normalizeRules($rules, $internalIp, $remote['host']);
        if ($isNat) {
            $connection = collect($normalized)->first(fn (array $rule): bool => $rule['internal_port'] === $defaultPort && in_array($rule['protocol'], ['tcp', 'tcp+udp'], true));
            if ($connection) {
                $remote = RemoteEndpoint::parse($connection['external_ip'] ?: $remote['host'], $connection['external_port'], $defaultPort);
            }
        } elseif ($remote['host'] === '') {
            $remote = RemoteEndpoint::parse($internalIp, null, $defaultPort);
        }
        $minimum = (int) ($info['min_port'] ?? 0);
        $maximum = (int) ($info['max_port'] ?? 0);
        $reserved = preg_split('/[,\s]+/', (string) ($info['no_port'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        DB::transaction(function () use ($service, $info, $normalized, $internalIp, $remote, $windows, $os, $isNat, $minimum, $maximum, $reserved, $hostId): void {
            $current = Service::query()->lockForUpdate()->findOrFail($service->id);
            $specs = (array) $current->specs;
            foreach (['cpu' => 'cpu', 'memory_gb' => 'memory', 'upload_mbps' => 'bandwidth', 'download_mbps' => 'bandwidth_in'] as $target => $source) {
                if (isset($info[$source]) && is_numeric($info[$source])) {
                    $specs[$target] = (int) $info[$source];
                }
            }
            if (array_key_exists('hard_disks', $info)) {
                $specs['data_disk_gb'] = (int) $info['hard_disks'];
                $specs['disk_gb'] = (int) ($info['os_size'] ?? 0) > 0 ? (int) $info['os_size'] : null;
            }
            $specs['bandwidth_mbps'] = (int) ($info['bandwidth'] ?? $current->bandwidth_mbps);
            $specs['internal_ip'] = $isNat ? $internalIp : null;
            $specs['ostype'] = $os;
            $specs['nat_ports'] = (int) ($info['port_num'] ?? 0);
            $specs['qzvps'] = array_merge((array) ($specs['qzvps'] ?? []), array_intersect_key($info, array_flip(['line_id', 'node_id', 'line_name', 'node_name', 'area_name', 'host_name', 'cpu_model', 'os_name', 'is_nat', 'port_num', 'state', 'is_vnc', 'virtual_type', 'os_size', 'snapshot_num', 'backup_num', 'firewall_enable', 'max_reinstall_num', 'reinstall_num', 'end_time'])), ['host_id' => $hostId, 'remote_ip' => $info['remote_ip'] ?? '', 'min_port' => $minimum, 'max_port' => $maximum, 'reserved_ports' => array_values(array_filter(array_map('intval', $reserved))), 'synced_at' => now()->toIso8601String()]);
            $specs['remote'] = array_merge((array) ($specs['remote'] ?? []), ['address' => $remote['host'], 'port' => $remote['port'], 'protocol' => $windows ? 'rdp' : 'ssh', 'username' => $windows ? 'Administrator' : 'root']);
            if (! empty($info['os_password'])) {
                $specs['remote']['password'] = (string) $info['os_password'];
            }
            unset($specs['network_sync_error'], $specs['qzvps']['sync_error']);
            $updates = ['os' => mb_substr($os, 0, 80), 'ip_address' => filter_var($internalIp, FILTER_VALIDATE_IP) ? $internalIp : $current->ip_address, 'nat_ip' => $isNat ? ($remote['host'] ?: null) : null, 'nat_port_start' => $isNat && $minimum > 0 ? $minimum : null, 'nat_port_end' => $isNat && $maximum >= $minimum ? $maximum : null, 'bandwidth_mbps' => $specs['bandwidth_mbps'], 'specs' => $specs];
            if (! empty($info['line_name'])) {
                $updates['region'] = mb_substr((string) $info['line_name'], 0, 64);
            }
            $current->update($updates);
            $ids = [];
            foreach ($normalized as $rule) {
                $mapping = NatMapping::updateOrCreate(['service_id' => $current->id, 'external_port' => $rule['external_port'], 'internal_port' => $rule['internal_port'], 'internal_ip' => $rule['internal_ip']], ['external_ip' => $rule['external_ip'] ?: $remote['host'], 'protocol' => $rule['protocol'], 'status' => 'active']);
                $ids[] = $mapping->id;
            }
            $current->natMappings()->whereNotIn('id', $ids)->delete();
        });
        $service->refresh()->load('natMappings');

        return $info;
    }

    /** @return array<int, array{external_port: int, internal_port: int, external_ip: string, internal_ip: string, protocol: string}> */
    public function normalizeRules(array $rules, string $internalIp, string $publicHost): array
    {
        $groups = [];
        foreach ($rules as $rule) {
            $external = (int) ($rule['sport'] ?? $rule['external_port'] ?? 0);
            $internal = (int) ($rule['dport'] ?? $rule['internal_port'] ?? 0);
            $ip = (string) ($rule['dip'] ?? $rule['internal_ip'] ?? $internalIp);
            if ($external < 1 || $external > 65535 || $internal < 1 || $internal > 65535 || $ip === '') {
                throw new RuntimeException('轻舟端口映射数据不完整，已保留本地记录。');
            }
            $protocol = strtolower((string) ($rule['port_type'] ?? $rule['type'] ?? $rule['protocol'] ?? 'tcp'));
            $protocol = in_array($protocol, ['all', 'both', 'tcp/udp', 'tcp+udp'], true) ? 'tcp+udp' : $protocol;
            if (! in_array($protocol, ['tcp', 'udp', 'tcp+udp'], true)) {
                throw new RuntimeException('轻舟返回了无法识别的映射协议。');
            }
            $key = $external.'|'.$ip.'|'.$internal;
            if (isset($groups[$key]) && $groups[$key]['protocol'] !== $protocol) {
                $protocol = 'tcp+udp';
            }
            $groups[$key] = ['external_port' => $external, 'internal_port' => $internal, 'external_ip' => $publicHost, 'internal_ip' => $ip, 'protocol' => $protocol];
        }

        return array_values($groups);
    }
}
