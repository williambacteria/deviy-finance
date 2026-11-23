<?php

namespace App\Services\Pterodactyl;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PterodactylClient
{
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $apiKey,
        private readonly ?string $clientApiKey = null,
    ) {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $baseUrl = preg_replace('#/api/(application|client)$#', '', $baseUrl) ?: $baseUrl;
        $this->baseUrl = $baseUrl;
    }

    /** @return array<int, array<string, mixed>> */
    public function nodes(): array
    {
        return $this->items($this->application('get', '/nodes'));
    }

    /** @return array<int, array<string, mixed>> */
    public function nests(): array
    {
        return $this->items($this->application('get', '/nests'));
    }

    /** @return array<int, array<string, mixed>> */
    public function eggs(int $nestId): array
    {
        return $this->items($this->application('get', '/nests/'.$nestId.'/eggs'));
    }

    /** @return array<string, mixed> */
    public function egg(int $nestId, int $eggId): array
    {
        return (array) $this->application('get', '/nests/'.$nestId.'/eggs/'.$eggId, ['include' => 'variables']);
    }

    /** @return array<int, array<string, mixed>> */
    public function users(string $email = ''): array
    {
        $query = $email !== '' ? ['filter' => ['email' => $email]] : [];

        return $this->items($this->application('get', '/users', $query));
    }

    /** @return array<string, mixed> */
    public function createUser(array $payload): array
    {
        return (array) $this->application('post', '/users', $payload);
    }

    /** @return array<string, mixed> */
    public function updateUser(int $userId, array $payload): array
    {
        return (array) $this->application('patch', '/users/'.$userId, $payload);
    }

    /** @return array<int, array<string, mixed>> */
    public function servers(): array
    {
        return $this->items($this->application('get', '/servers'));
    }

    /** @return array<string, mixed> */
    public function createServer(array $payload): array
    {
        return (array) $this->application('post', '/servers', $payload);
    }

    public function suspendServer(string $serverId): void
    {
        $this->application('post', '/servers/'.$serverId.'/suspend');
    }

    public function unsuspendServer(string $serverId): void
    {
        $this->application('post', '/servers/'.$serverId.'/unsuspend');
    }

    public function serverExists(string $serverId): bool
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('翼龙面板地址或 API Key 未配置。');
        }
        $response = Http::withToken($this->apiKey)->acceptJson()->timeout(20)
            ->get($this->baseUrl.'/api/application/servers/'.rawurlencode($serverId));
        $body = $response->json();
        if ($response->status() === 404 && in_array((string) data_get($body, 'errors.0.code'), ['NotFoundHttpException', 'NotFoundException', 'ModelNotFoundException', 'NotFound'], true)) {
            return false;
        }
        $response->throw();
        if ((string) data_get($body, 'attributes.id') !== $serverId) {
            throw new RuntimeException('翼龙返回的服务器编号不匹配，无法核对删除结果。');
        }

        return true;
    }

    public function deleteServer(string $serverId): void
    {
        $this->application('delete', '/servers/'.$serverId);
    }

    public function power(string $serverId, string $signal): void
    {
        $this->client('post', '/servers/'.$serverId.'/power', ['signal' => $signal]);
    }

    public function reinstall(string $serverId): void
    {
        $this->client('post', '/servers/'.$serverId.'/reinstall');
    }

    /** @return array<string, mixed> */
    private function application(string $method, string $path, array $payload = []): array
    {
        return $this->send($method, '/api/application'.$path, $payload, $this->apiKey);
    }

    /** @return array<string, mixed> */
    private function client(string $method, string $path, array $payload = []): array
    {
        if (! $this->clientApiKey) {
            throw new RuntimeException('未配置翼龙客户端 API Key，无法执行电源或重装操作。');
        }

        return $this->send($method, '/api/client'.$path, $payload, $this->clientApiKey);
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $payload, string $token): array
    {
        if ($this->baseUrl === '' || $token === '') {
            throw new RuntimeException('翼龙面板地址或 API Key 未配置。');
        }

        $request = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20);

        /** @var PendingRequest $request */
        $response = match ($method) {
            'get' => $request->get($this->baseUrl.$path, $payload),
            'post' => $request->post($this->baseUrl.$path, $payload),
            'patch' => $request->patch($this->baseUrl.$path, $payload),
            'delete' => $request->delete($this->baseUrl.$path, $payload),
            default => throw new RuntimeException('不支持的请求方法：'.$method),
        };

        if ($response->failed()) {
            $message = (string) (data_get($response->json(), 'errors.0.detail') ?: data_get($response->json(), 'errors.0.code') ?: $response->body());
            throw new RuntimeException('翼龙 API 请求失败（'.$response->status().'）：'.mb_substr($message, 0, 300));
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function items(array $payload): array
    {
        $data = data_get($payload, 'data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}
