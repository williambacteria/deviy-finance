<?php

namespace App\Services\Qzvps;

use App\Models\ProvisionTask;
use App\Models\Service;
use Illuminate\Support\Str;
use RuntimeException;

class QzvpsProvisioner
{
    public function __construct(private QzvpsClient $client) {}

    public function dispatch(ProvisionTask $task, Service $service): void
    {
        $operation = (string) $task->operation;

        if ($operation === 'create') {
            $this->create($task, $service);

            return;
        }

        $hostId = $this->hostId($service);
        if ($hostId <= 0) {
            throw new RuntimeException('服务尚未绑定轻舟主机 ID。');
        }

        $result = match ($operation) {
            'suspend' => $this->client->action($hostId, 'lock'),
            'unsuspend' => $this->client->action($hostId, 'unlock'),
            'stop' => $this->client->action($hostId, 'shutdown'),
            'start' => $this->client->action($hostId, 'start'),
            'reboot' => $this->client->action($hostId, 'reboot'),
            'terminate' => $this->client->action($hostId, 'delete'),
            'reinstall' => $this->reinstall($service, $hostId),
            'renew' => $this->client->renew($hostId, $service->expires_at?->format('Y-m-d H:i:s') ?? now()->addMonth()->format('Y-m-d H:i:s')),
            default => throw new RuntimeException('轻舟暂不支持该操作：'.$operation),
        };

        $service->update([
            'status' => match ($operation) {
                'suspend', 'stop' => 'suspended',
                'terminate' => 'terminated',
                'unsuspend', 'start', 'reboot', 'reinstall', 'renew' => 'active',
                default => $service->status,
            },
            'provision_status' => 'succeeded',
        ]);

        $task->update([
            'status' => 'succeeded',
            'completed_at' => now(),
            'last_error' => null,
            'result' => $result,
        ]);
    }

    private function create(ProvisionTask $task, Service $service): void
    {
        if ($this->hostId($service) > 0) {
            $task->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'last_error' => null,
                'result' => ['host_id' => $this->hostId($service), 'reused' => true],
            ]);

            return;
        }

        $service->loadMissing(['product', 'user']);
        $qzvps = (array) data_get($service->product?->metadata, 'qzvps', []);
        $specs = is_array($service->specs) ? $service->specs : [];
        $lineId = (string) ($qzvps['line_id'] ?? '');
        $nodeId = (string) ($qzvps['node_id'] ?? '');
        if ($lineId === '' && $nodeId === '') {
            throw new RuntimeException('商品未配置轻舟线路或节点。');
        }

        $hostName = $this->hostName($service);
        $password = (string) ($specs['remote']['password'] ?? '');
        if ($password === '') {
            $password = Str::upper(Str::random(3)).Str::lower(Str::random(9)).random_int(100000, 999999);
            $specs['remote'] = array_merge((array) ($specs['remote'] ?? []), ['password' => $password]);
        }

        $osName = (string) ($specs['ostype'] ?? '');
        if (! preg_match('/^(WindowsServer|Centos|Debian|Ubuntu)/i', $osName)) {
            $osName = (string) ($qzvps['os_name'] ?? $specs['qzvps']['os_name'] ?? 'Ubuntu22.04');
        }

        $payload = [
            'line_id' => $lineId,
            'nodes_id' => $nodeId,
            'cpu' => (int) ($specs['cpu'] ?? 1),
            'memory' => (int) ($specs['memory_gb'] ?? 1),
            'hard_disks' => data_get($service->product?->metadata, 'pricing_review.included_data_disk_gb') !== null ? (int) ($specs['data_disk_gb'] ?? 50) : (int) ($specs['disk_gb'] ?? 40),
            'bandwidth' => (int) ($specs['upload_mbps'] ?? $specs['bandwidth_mbps'] ?? 10),
            'bandwidth_in' => (int) ($specs['download_mbps'] ?? 100),
            'os' => $osName,
            'host_name' => $hostName,
            'expire_time' => $service->expires_at?->format('Y-m-d H:i:s') ?? now()->addMonth()->format('Y-m-d H:i:s'),
            'sys_pwd' => $password,
            'vnc_pwd' => $password,
            'ipnum' => ($qzvps['ipnum'] ?? 0) == 0 ? 0 : (int) $qzvps['ipnum'],
            'port_num' => (int) ($qzvps['port_num'] ?? $specs['nat_ports'] ?? 10),
            'metal' => (int) ($qzvps['metal'] ?? 0),
            'open_gpu' => (int) ($qzvps['open_gpu'] ?? 0),
        ];

        if (data_get($service->product?->metadata, 'pricing_review.included_data_disk_gb') !== null) {
            $payload['os_size'] = (int) ($specs['disk_gb'] ?? 50);
        }

        $result = $this->client->createHost($payload);
        $data = (array) ($result['data'] ?? []);
        $hostId = (int) ($data['host_id'] ?? $data['id'] ?? 0);
        if ($hostId <= 0) {
            throw new RuntimeException('轻舟未返回主机 ID。');
        }

        $this->pruneDefaultRemoteMapping($this->client, $hostId, $osName);

        $info = [];
        try {
            $info = (array) ($this->client->hostInfo($hostId)['data'] ?? []);
        } catch (\Throwable) {
            // The create response already contains the essential host data.
        }

        $remote = $this->remoteAddress($info);
        $specs['internal_ip'] = (string) ($info['ip'] ?? $data['ip'] ?? '');
        $specs['remote'] = array_merge((array) ($specs['remote'] ?? []), [
            'address' => $remote['host'] ?: (string) ($info['remote_ip'] ?? ''),
            'port' => $remote['port'],
            'username' => str_contains(strtolower((string) ($info['os_name'] ?? $payload['os'])), 'windows') ? 'Administrator' : 'root',
            'password' => $password,
        ]);
        $specs['qzvps'] = [
            'host_id' => $hostId,
            'line_id' => (int) ($info['line_id'] ?? $lineId),
            'node_id' => (int) ($info['node_id'] ?? $nodeId),
            'host_name' => $hostName,
            'os_name' => (string) ($info['os_name'] ?? $payload['os']),
            'is_nat' => (int) ($info['is_nat'] ?? $payload['ipnum'] === 0),
            'remote_ip' => (string) ($info['remote_ip'] ?? ''),
        ];

        $update = [
            'status' => 'active',
            'provision_status' => 'succeeded',
            'external_ref' => 'qzvps:'.$hostId,
            'specs' => $specs,
            'ip_address' => (string) ($info['ip'] ?? $data['ip'] ?? ''),
        ];
        if ($remote['host'] !== '') {
            $update['nat_ip'] = $remote['host'];
            $update['nat_port_start'] = $remote['port'];
            $update['nat_port_end'] = $remote['port'];
        }
        $service->update($update);

        $task->update([
            'status' => 'succeeded',
            'completed_at' => now(),
            'last_error' => null,
            'result' => $result,
        ]);
        $service->loadMissing('user');
        if ($service->user) {
            \App\Services\Mail\MailSettings::sendTemplate('service_provisioned', $service->user, ['service_name' => $service->name, 'service_no' => $service->service_no, 'date' => $service->expires_at?->format('Y-m-d H:i'), 'console_url' => route('console.services.show', $service)]);
        }

    }

    private function reinstall(Service $service, int $hostId): array
    {
        $qzvps = (array) data_get($service->product?->metadata, 'qzvps', []);
        $specs = is_array($service->specs) ? $service->specs : [];
        $template = (string) ($qzvps['os_name'] ?? $specs['qzvps']['os_name'] ?? 'Ubuntu22.04');
        $password = (string) ($specs['remote']['password'] ?? Str::password(16, symbols: false));

        return $this->client->resetOs($hostId, $template, $password);
    }

    private function pruneDefaultRemoteMapping(QzvpsClient $client, int $hostId, string $osName): void
    {
        $isWindows = str_contains(Str::lower($osName), 'windows');
        $keepPort = $isWindows ? 3389 : 22;

        try {
            $rules = $client->natAclList($hostId);
        } catch (\Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($rules as $rule) {
            $internalPort = (int) ($rule['dport'] ?? $rule['internal_port'] ?? 0);
            if (! in_array($internalPort, [22, 3389], true) || $internalPort === $keepPort) {
                continue;
            }

            $aclId = (int) ($rule['id'] ?? $rule['acl_id'] ?? 0);
            if ($aclId <= 0) {
                continue;
            }

            try {
                $client->removeNatAcl($hostId, $aclId);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    private function hostId(Service $service): int
    {
        if (preg_match('/^qzvps:(\d+)$/', (string) $service->external_ref, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function hostName(Service $service): string
    {
        $base = Str::lower((string) ($service->service_no ?: 'deviy-'.$service->id));
        $base = preg_replace('/[^a-z0-9-]/', '-', $base) ?: 'deviy-'.$service->id;

        return Str::limit(trim($base, '-'), 40, '');
    }

    /**
     * @return array{host: string, port: int}
     */
    private function remoteAddress(array $info): array
    {
        $remote = (string) ($info['remote_ip'] ?? '');
        if ($remote === '') {
            return ['host' => '', 'port' => 0];
        }

        if (str_contains($remote, ':')) {
            [$host, $port] = explode(':', $remote, 2);

            return ['host' => $host, 'port' => (int) $port];
        }

        return ['host' => $remote, 'port' => 0];
    }
}
