<?php

namespace App\Services\Qzvps;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QzvpsClient
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?: (string) config('services.qzvps.url', 'https://vps.deviy.cn'), '/');
        $this->apiKey = $apiKey ?: (string) config('services.qzvps.api_key', '');
    }

    public function hostInfo(int $hostId): array
    {
        return $this->request('GET', 'hostinfo/'.$hostId, ['host_id' => $hostId], $hostId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function hostExists(int $hostId): bool
    {
        try {
            $body = $this->hostInfo($hostId);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === '轻舟接口错误：云主机不存在') {
                return false;
            }
            throw $exception;
        }
        if ((int) data_get($body, 'data.id', data_get($body, 'data.host_id')) !== $hostId) {
            throw new RuntimeException('轻舟返回的主机编号不匹配，无法核对删除结果。');
        }

        return true;
    }

    public function lines(): array
    {
        $result = $this->request('GET', 'line', ['host_id' => 0], 0);

        return is_array($result['data'] ?? null) ? $result['data'] : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function images(): array
    {
        $result = $this->request('GET', 'mirror_image', ['host_id' => 0], 0);

        return is_array($result['data'] ?? null) ? $result['data'] : [];
    }

    public function createHost(array $payload): array
    {
        return $this->request('POST', 'create_host', $payload, 0);
    }

    public function action(int $hostId, string $action): array
    {
        return $this->request('POST', $action, ['host_id' => $hostId], $hostId);
    }

    public function resetOs(int $hostId, string $template, string $password): array
    {
        return $this->request('POST', 'reset_os', [
            'host_id' => $hostId,
            'template_id' => $template,
            'password' => $password,
        ], $hostId);
    }

    public function resetPassword(int $hostId, string $password): array
    {
        return $this->request('POST', 'reset_password', [
            'host_id' => $hostId,
            'password' => $password,
        ], $hostId);
    }

    public function renew(int $hostId, string $expireTime): array
    {
        return $this->request('POST', 'renew', [
            'host_id' => $hostId,
            'nextduedate' => $expireTime,
        ], $hostId);
    }

    public function monitor(int $hostId): array
    {
        return $this->request('GET', 'monitor', ['host_id' => $hostId], $hostId);
    }

    public function vncUrl(int $hostId): string
    {
        return $this->baseUrl.'/control/ecs/vnc?'.http_build_query([
            'hostid' => $hostId,
            'token' => md5($hostId.$this->apiKey),
        ]);
    }

    /**
     * 客户控制台与后台使用的 VNC 地址：不同宿主机节点使用不同的 VNC 门禁。
     */
    public function consoleUrl(int $hostId): string
    {
        $url = $this->vncUrl($hostId);

        try {
            $hostInfo = (array) ($this->hostInfo($hostId)['data'] ?? []);
        } catch (\Throwable $exception) {
            report($exception);

            return $url;
        }

        if ((int) ($hostInfo['node_id'] ?? 0) === 7) {
            return 'https://vps.deviy.cn/vnc-p9.html?'.http_build_query([
                'hostid' => $hostId,
                'token' => (string) ($hostInfo['host_name'] ?? ''),
                'password' => (string) ($hostInfo['vnc_password'] ?? ''),
            ]);
        }

        return $url;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function natAclList(int $hostId): array
    {
        $result = $this->request('GET', 'nat_acl_list/'.$hostId, ['host_id' => $hostId], $hostId);

        return is_array($result['data'] ?? null) ? $result['data'] : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addNatAcl(int $hostId, array $data): array
    {
        return $this->request('GET', 'add_port_host', array_merge([
            'host_id' => $hostId,
        ], $data), $hostId);
    }

    /**
     * @return array<int, int>
     */
    public function availablePorts(int $hostId): array
    {
        $result = $this->request('GET', 'available_ports/'.$hostId, ['host_id' => $hostId], $hostId);

        return array_values(array_filter(array_map('intval', (array) ($result['data'] ?? []))));
    }

    public function removeNatAcl(int $hostId, int $aclId): array
    {
        return $this->request('GET', 'remove_port_host', [
            'hostid' => $hostId,
            'id' => $aclId,
        ], $hostId);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $data, int $hostId): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('轻舟 API 密钥未配置。');
        }

        $signature = md5($this->apiKey.$hostId);
        $query = ['host_id' => $hostId, 'signature' => $signature];
        if ($method === 'GET') {
            $query = array_merge($query, $data);
        }
        $url = $this->baseUrl.'/api/whmcs/'.ltrim($path, '/').'?'.http_build_query($query);

        $request = Http::acceptJson()
            ->withHeaders([
                'apikey' => $this->apiKey,
                'User-Agent' => 'WHMCS',
            ])
            ->timeout((int) config('services.qzvps.timeout', 30))
            ->withOptions(['verify' => (bool) config('services.qzvps.verify_tls', false)]);

        $response = $method === 'GET'
            ? $request->get($url)
            : $request->asForm()->post($url, $data);

        return $this->decode($response);
    }

    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('轻舟接口 HTTP 错误：'.$response->status());
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('轻舟接口返回了无法解析的数据。');
        }

        if ((int) ($body['code'] ?? 0) !== 1) {
            $message = (string) ($body['msg'] ?? $body['message'] ?? '未知错误');

            throw new RuntimeException('轻舟接口错误：'.$message);
        }

        return $body;
    }
}
