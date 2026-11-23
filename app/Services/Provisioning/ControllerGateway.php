<?php

namespace App\Services\Provisioning;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Finance-facing client for the Deviy Controller.
 *
 * The Controller is the only component that talks to the hypervisor agents:
 * KVM, Proxmox VE, Hyper-V and bare-metal nodes can therefore use the same
 * finance integration instead of talking to each node directly.
 */
class ControllerGateway
{
    public function __construct(private ?PendingRequest $http = null) {}

    /** @return array<int, array<string, mixed>> */
    public function nodes(): array
    {
        $items = $this->items($this->client()->get('/api/nodes')->throw()->json());

        return array_map(function (array $node): array {
            unset($node['token'], $node['agent_token'], $node['cert_fingerprint']);

            return $node;
        }, $items);
    }

    /** @return array<string, mixed>|null */
    public function node(string $nodeId): ?array
    {
        foreach ($this->nodes() as $node) {
            if ((string) ($node['id'] ?? '') === $nodeId) {
                return $node;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function overview(string $nodeId): array
    {
        $payload = $this->client()->get('/api/nodes/'.$this->seg($nodeId).'/overview')->throw()->json();

        return is_array($payload) ? $payload : [];
    }

    /** @return array<string, mixed> */
    public function createHost(array $payload): array
    {
        $response = $this->client()->post('/api/hosts', $payload)->throw()->json();

        return is_array($response) ? $response : [];
    }

    /** @return array<string, mixed> */
    public function virtualMachines(string $nodeId): array
    {
        return $this->items($this->client()->get('/api/nodes/'.$this->seg($nodeId).'/vms')->throw()->json());
    }

    public function virtualMachineExists(string $nodeId, string $vmid): bool
    {
        $response = $this->client()->get('/api/nodes/'.$this->seg($nodeId).'/vms')->throw();
        $body = $response->json();
        $items = is_array($body) && array_is_list($body) ? $body : (is_array($body) ? ($body['items'] ?? null) : null);
        if (! is_array($items) || ! array_is_list($items)) {
            throw new \RuntimeException('主控实例列表返回结构异常，无法核对删除结果。');
        }
        foreach ($items as $item) {
            if (! is_array($item) || (string) ($item['id'] ?? $item['vmid'] ?? '') === '') {
                throw new \RuntimeException('主控实例列表缺少资源编号，无法核对删除结果。');
            }
            if ((string) ($item['id'] ?? $item['vmid']) === $vmid) {
                return true;
            }
        }

        return false;
    }

    public function deleteVirtualMachine(string $nodeId, string $vmid): void
    {
        $response = $this->client()->post('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/action', ['action' => 'delete'])->throw();
        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? null) === false || ! empty($body['error'])) {
            throw new \RuntimeException('主控没有确认接收销毁请求。');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function natRules(string $nodeId): array
    {
        return $this->items($this->client()->get('/api/nodes/'.$this->seg($nodeId).'/nat')->throw()->json());
    }

    public function deleteNatRule(string $nodeId, string $ruleId): void
    {
        $this->client()
            ->delete('/api/nodes/'.$this->seg($nodeId).'/nat/'.$this->seg($ruleId))
            ->throw();
    }

    /** @return array<string, mixed> */
    public function createNatRule(string $nodeId, array $payload): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/nat', $payload)
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    /** @return array<string, mixed> */
    public function applyNetwork(string $nodeId, string $kind = 'nat'): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/network/apply', [
                'kind' => $kind,
                'confirm' => true,
            ])
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function changeGuestPassword(string $nodeId, string $vmid, string $internalIp, string $username, string $password): void
    {
        $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/guest/password', [
                'ip' => $internalIp,
                'username' => $username,
                'password' => $password,
                'token' => '',
            ])
            ->throw();
    }

    /** @return array<string, mixed> */
    public function allocateNat(string $nodeId, array $payload): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/nat/allocate', $payload)
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    /** @return array<string, mixed> */
    public function configureGuestNetwork(string $nodeId, string $vmid, array $payload): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/guest/network', $payload)
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    /** @return array<string, mixed> */
    public function reinstallVirtualMachine(string $nodeId, string $vmid, array $payload): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/reinstall', $payload)
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function configureGuestDns(string $nodeId, string $vmid, string $internalIp, string $dns1, string $dns2): void
    {
        $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/guest/dns', [
                'ip' => $internalIp,
                'dns1' => $dns1,
                'dns2' => $dns2,
            ])
            ->throw();
    }

    /** @return array<int, array<string, mixed>> */
    public function images(?string $nodeId = null): array
    {
        $items = $this->items($this->client()->get('/api/images')->throw()->json());

        if ($nodeId === null) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn (array $image): bool => (string) ($image['node_id'] ?? '') === $nodeId
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function ipPools(?string $nodeId = null): array
    {
        $items = $this->items($this->client()->get('/api/ip-pools')->throw()->json());

        if ($nodeId === null) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn (array $pool): bool => $pool['node_id'] === null || (string) $pool['node_id'] === $nodeId
        ));
    }

    /** @return array<string, mixed> */
    public function createVirtualMachine(string $nodeId, array $payload): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/vms', $payload)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Controller 返回了无效的创建结果。');
        }

        return $response;
    }

    /** @return array<string, mixed> */
    public function action(string $nodeId, string $vmid, string $action): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/action', [
                'action' => $action,
            ])
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    /** @return array<string, mixed> */
    public function vncTicket(string $nodeId, string $vmid): array
    {
        $response = $this->client()
            ->post('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/vnc-ticket')
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    /** @return array<string, mixed> */
    public function vmStats(string $nodeId, string $vmid): array
    {
        $response = $this->client()
            ->get('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/stats')
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    /** @return array<string, mixed> */
    public function vmUsage(string $nodeId, string $vmid): array
    {
        $response = $this->client()
            ->get('/api/nodes/'.$this->seg($nodeId).'/vms/'.$this->seg($vmid).'/usage')
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function publicUrl(): string
    {
        return rtrim((string) config('deviy.controller_public_url'), '/');
    }

    public function configured(): bool
    {
        return trim((string) config('deviy.controller_url')) !== ''
            && trim((string) config('deviy.controller_token')) !== '';
    }

    private function client(): PendingRequest
    {
        if ($this->http !== null) {
            return $this->http;
        }

        $client = Http::baseUrl(rtrim((string) config('deviy.controller_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('deviy.timeout', 30));

        $token = trim((string) config('deviy.controller_token'));
        if ($token !== '') {
            $client = $client->withToken($token);
        }

        $options = [];
        $ca = trim((string) config('deviy.controller_ca'));
        $cert = trim((string) config('deviy.controller_cert'));
        $key = trim((string) config('deviy.controller_key'));
        if ($ca !== '') {
            $options['verify'] = $ca;
        }
        if ($cert !== '' && $key !== '') {
            $options['cert'] = $cert;
            $options['ssl_key'] = $key;
        }
        if ($options !== []) {
            $client = $client->withOptions($options);
        }

        return $client;
    }

    /** @return array<int, array<string, mixed>> */
    private function items(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $items = $payload['items'] ?? $payload;
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    private function seg(string $value): string
    {
        return rawurlencode($value);
    }
}
