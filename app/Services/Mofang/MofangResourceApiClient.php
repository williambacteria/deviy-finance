<?php

namespace App\Services\Mofang;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MofangResourceApiClient
{
    private CookieJar $cookies;

    private ?string $jwt = null;

    /** @param array<string, string> $endpoints */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly int $timeout,
        private readonly array $endpoints,
        private readonly bool $verifySsl = true,
    ) {
        $this->cookies = new CookieJar;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createOrder(array $payload): array
    {
        $productId = (string) ($payload['product_id'] ?? '');
        if ($productId === '') {
            throw new RuntimeException('上游订单缺少商品 ID。');
        }

        $config = $this->get('product_config', ['pid' => $productId]);
        $fields = [
            'pid' => $productId,
            'billingcycle' => (string) ($payload['billing_cycle'] ?? 'monthly'),
            'qty' => (string) max(1, (int) ($payload['quantity'] ?? 1)),
            'currencyid' => '1',
            'host' => (string) ($payload['host'] ?? 'DV'.substr(hash('sha256',(string)($payload['service_no']??Str::uuid())),0,12)),
            'password' => (string) ($payload['password'] ?? Str::password(16)),
            'checkout' => '0',
        ];

        foreach ((array) data_get($config, 'data.config_groups', []) as $group) {
            foreach ((array) ($group['options'] ?? []) as $option) {
                $id = (string) ($option['id'] ?? '');
                $choices = (array) ($option['sub'] ?? []);
                if ($id !== '' && $choices !== []) {
                    $fields['configoption['.$id.']'] = (string) ((int) ($option['option_type'] ?? 0) === 14 ? 0 : ($choices[0]['id'] ?? ''));
                }
            }
        }
        foreach ((array) ($payload['configoption'] ?? []) as $id => $value) {
            $id = preg_replace('/\D+/', '', (string) $id);
            if ($id !== '') {
                $fields['configoption['.$id.']'] = (string) $value;
            }
        }

        $added = $this->post('cart_add', $fields);
        $position = (string) (data_get($added, 'data.i') ?? 0);
        $settled = $this->post('cart_settle', ['payment' => 'AliPay', 'pos' => [$position], 'checkout' => '1']);
        $orderId = (string) (data_get($settled, 'data.orderid') ?? data_get($settled, 'data.order_id') ?? data_get($settled, 'data.invoiceid') ?? '');
        if ($orderId === '') {
            throw new RuntimeException('魔方上游结算未返回订单号。');
        }

        $hostIds = data_get($settled, 'data.hostid') ?? data_get($settled, 'data.host_ids') ?? [];

        return [
            'order_id' => $orderId,
            'billing_id' => (string) (data_get($settled, 'data.invoiceid') ?? ''),
            'invoice_id' => (string) (data_get($settled, 'data.invoiceid') ?? ''),
            'service_id' => is_array($hostIds) ? (string) reset($hostIds) : (string) $hostIds,
            'status' => 'unpaid',
            'data' => $settled['data'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function payOrder(string $invoiceId): array
    {
        return ['status' => 'paid', 'data' => $this->post('apply_credit', ['id' => $invoiceId, 'invoiceid' => $invoiceId])];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function renewService(string $serviceId, array $payload): array
    {
        $result = $this->post('renewals', [
            'hostid' => $serviceId,
            'billingcycles' => (string) ($payload['billing_cycle'] ?? 'monthly'),
            'duration' => (string) ((int) ($payload['months'] ?? 1) * 30 * 86400),
        ]);
        $invoiceId = (string) (data_get($result, 'data.invoiceid') ?? data_get($result, 'data.invoice_id') ?? '');
        if ($invoiceId === '') { throw new RuntimeException('续费请求未返回账单 ID，请核对上游账单后处理。'); }
        if (($payload['invoice_only']??false) === true) { return ['status'=>'unpaid','invoice_id'=>$invoiceId,'billing_id'=>$invoiceId,'data'=>$result['data']??[]]; }
        $this->payOrder($invoiceId);

        return ['status' => 'active', 'data' => $result['data'] ?? []];
    }

    /**
     * Find the upstream service created for a paid invoice when the payment
     * endpoint does not return a service/host ID.
     */
    public function findServiceId(string $invoiceId, string $email = '', string $productId = ''): ?string
    {
        $result = $this->get('service_list', ['limit' => 100, 'page' => 1]);
        $items = (array) (data_get($result, 'data.list') ?? data_get($result, 'list') ?? []);
        $fallback = null;
        $fallbackDate = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $data = $item;
            foreach (['host_data', 'service', 'host'] as $key) {
                if (is_array(data_get($data, $key))) {
                    $data = array_replace($data, data_get($data, $key));
                }
            }
            $id = (string) (data_get($data, 'id') ?? data_get($data, 'host_id') ?? '');
            if ($id === '') {
                continue;
            }
            if ($invoiceId !== '' && (string) (data_get($data, 'invoice_id') ?? data_get($data, 'invoiceid') ?? '') === $invoiceId) {
                return $id;
            }

            $sameProduct = $productId !== '' && (string) (data_get($data, 'pid') ?? data_get($data, 'product_id') ?? '') === $productId;
            $sameEmail = $email !== '' && strcasecmp((string) (data_get($data, 'email') ?? data_get($data, 'user_email') ?? ''), $email) === 0;
            $active = in_array(strtolower((string) (data_get($data, 'domainstatus') ?? data_get($data, 'status') ?? '')), ['active', 'paid', 'success'], true);
            if (! $active || (! $sameProduct && ! $sameEmail)) {
                continue;
            }
            $date = (int) (data_get($data, 'regdate') ?? data_get($data, 'create_time') ?? 0);
            if ($date >= $fallbackDate) {
                $fallback = $id;
                $fallbackDate = $date;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function fetchService(string $serviceId): array
    {
        try {
            return $this->get('services', ['host_id' => $serviceId]);
        } catch (RuntimeException) {
            $list = $this->get('service_list', ['search' => $serviceId, 'keywords' => $serviceId, 'limit' => 100]);
            $items = (array) (data_get($list, 'data.list') ?? data_get($list, 'list') ?? []);
            foreach ($items as $item) {
                if (is_array($item) && ((string) ($item['domain'] ?? '') === $serviceId || (string) ($item['id'] ?? '') === $serviceId)) {
                    if (is_numeric($item['id'] ?? null)) {
                        try {
                            return $this->get('services', ['host_id' => (string) $item['id']]);
                        } catch (RuntimeException) {
                            // Fall back to the list record when this module exposes no detail endpoint.
                        }
                    }

                    return ['status' => $item['domainstatus'] ?? 'unknown', 'data' => $item, 'host_data' => $item];
                }
            }

            throw new RuntimeException('魔方上游未找到实例 '.$serviceId.'。');
        }
    }

    /** @return array<string, mixed> */
    public function fetchMetrics(string $serviceId): array
    {
        if ($this->isDcimHost($serviceId)) {
            $payload = $this->get('traffic', ['id' => $this->resolveHostId($serviceId)]);
            $points = array_values(array_filter((array) data_get($payload, 'data', []), fn ($point): bool => is_array($point) && isset($point['time']) && is_numeric($point['value'] ?? null)));

            return ['charts' => ['bandwidth' => ['unit' => 'GB', 'series' => [['name' => '流量', 'points' => $points]]]]];
        }

        $hostId = $this->resolveHostId($serviceId);
        if ($this->jwt === null) {
            $this->authenticate();
        }

        $types = ['cpu', 'memory', 'disk', 'flow'];
        $endpoint = str_replace('{id}', $hostId, $this->endpoint('metrics'));
        $responses = Http::pool(function ($pool) use ($endpoint, $types): array {
            $requests = [];
            foreach ($types as $type) {
                $request = $pool
                    ->withOptions(['cookies' => $this->cookies])
                    ->baseUrl(rtrim($this->baseUrl, '/'))
                    ->withToken($this->jwt)
                    ->acceptJson()
                    ->connectTimeout(min(5, $this->timeout))
                    ->timeout($this->timeout);
                if (! $this->verifySsl) {
                    $request->withoutVerifying();
                }
                $requests[$type] = $request->get($endpoint, ['type' => $type]);
            }

            return $requests;
        });

        $result = [];
        $units = [];
        $charts = [];
        $titles = ['cpu' => 'CPU使用量', 'memory' => '内存用量', 'disk' => '磁盘IO', 'flow' => '网卡'];
        $seriesNames = [
            'cpu' => ['CPU使用率'],
            'memory' => ['内存用量'],
            'disk' => ['读取速度', '写入速度'],
            'flow' => ['进', '出'],
        ];
        $defaultUnits = ['cpu' => '%', 'memory' => 'GB', 'disk' => 'MB/s', 'flow' => 'Mbps'];
        foreach ($responses as $key => $response) {
            $type = is_string($key) && in_array($key, $types, true) ? $key : null;
            if ($type === null) {
                try {
                    $query = [];
                    parse_str((string) parse_url($response->effectiveUri(), PHP_URL_QUERY), $query);
                    $type = (string) ($query['type'] ?? '');
                } catch (Throwable) {
                    continue;
                }
            }
            if (! in_array($type, $types, true)) {
                continue;
            }
            try {
                $payload = $this->payload($response->throw()->json());
            } catch (Throwable) {
                continue;
            }

            $series = (array) (data_get($payload, 'data.list') ?? []);
            if ($series === []) {
                continue;
            }
            $unit = trim((string) data_get($payload, 'data.unit', '')) ?: ($defaultUnits[$type] ?? '');
            $chartSeries = [];
            $seriesIndexes = $type === 'memory' ? [count($series) > 1 ? 1 : 0] : array_keys($series);
            foreach ($seriesIndexes as $index) {
                $points = array_values(array_filter((array) ($series[$index] ?? []), 'is_array'));
                if ($points === []) {
                    continue;
                }
                $chartSeries[] = [
                    'name' => $seriesNames[$type][count($chartSeries)] ?? $seriesNames[$type][0],
                    'points' => array_values(array_map(fn (array $point): array => [
                        'time' => (string) ($point['time'] ?? ''),
                        'value' => (float) ($point['value'] ?? 0),
                    ], $points)),
                ];
            }
            if ($chartSeries === []) {
                continue;
            }

            $points = $chartSeries[0]['points'];
            $values = array_map(fn (array $point): float => $point['value'], $points);
            if ($type === 'flow' && count($chartSeries) > 1) {
                $values = array_map(
                    fn (int $index): float => max((float) ($chartSeries[0]['points'][$index]['value'] ?? 0), (float) ($chartSeries[1]['points'][$index]['value'] ?? 0)),
                    array_keys($chartSeries[0]['points']),
                );
            }
            if ($values !== []) {
                $key = $type === 'flow' ? 'bandwidth' : $type;
                $result[$key] = $values;
                $units[$key] = $unit;
                $charts[$key] = [
                    'title' => $titles[$type],
                    'unit' => $unit,
                    'series' => $chartSeries,
                ];
            }
        }

        return ['data' => $result, 'units' => $units, 'charts' => $charts];
    }

    /** @return array<string, mixed> */
    public function fetchTraffic(string $serviceId): array
    {
        return $this->fetchService($serviceId);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function buyTrafficPackage(string $serviceId, array $payload): array
    {
        $packetId = filter_var($payload['flow_packet_id'] ?? $payload['flowpacketid'] ?? null, FILTER_VALIDATE_INT);
        if (! $packetId || $packetId < 1) {
            throw new RuntimeException('请选择有效的流量包。');
        }

        $request = [
            'id' => $this->resolveHostId($serviceId),
            'fid' => (string) $packetId,
        ];
        if (! empty($payload['idempotency_key'])) {
            $request['idempotency_key'] = (string) $payload['idempotency_key'];
        }

        return $this->post('buy_traffic', $request);
    }

    /** @return array<string, mixed> */
    public function fetchVnc(string $serviceId): array
    {
        $serviceId = $this->resolveHostId($serviceId);
        $dcim = $this->isDcimHost($serviceId);

        return $this->post($dcim ? $this->endpointKey('dcim_vnc', 'service_action') : 'service_action', [
            'id' => $serviceId,
            'hostid' => $serviceId,
            'func' => 'vnc',
            'is_api' => '1',
        ]);
    }

    /** @return array<string, mixed> */
    public function performAction(string $serviceId, string $action, array $payload = []): array
    {
        $serviceId = $this->resolveHostId($serviceId);
        $action = match ($action) {
            'start' => 'on',
            'stop', 'shutdown' => 'off',
            default => $action,
        };

        if (! in_array($action, ['on', 'off', 'reboot', 'reinstall', 'crack_pass', 'suspend', 'unsuspend', 'terminate', 'bmc', 'rescue'], true)) {
            throw new RuntimeException('不支持的魔方上游操作：'.$action);
        }

        $data = ['id' => $serviceId, 'func' => $action, 'is_api' => '1'];
        if ($action === 'reinstall') {
            $osId = filter_var($payload['os'] ?? null, FILTER_VALIDATE_INT);
            if (! $osId || $osId < 1) {
                throw new RuntimeException('请先选择要安装的操作系统。');
            }
            $data['os'] = (string) $osId;
        }
        if ($action === 'crack_pass') {
            $password = (string) ($payload['password'] ?? '');
            if (mb_strlen($password) < 8) {
                throw new RuntimeException('新密码至少需要 8 位。');
            }
            $data['password'] = $password;
            $data['new_password'] = $password;
            if (($payload['force_shutdown'] ?? false) || ($payload['force'] ?? false)) {
                $data['force'] = '1';
                $data['force_shutdown'] = '1';
            }
        }

        $data['hostid'] = $serviceId;
        $dcim = $this->isDcimHost($serviceId);
        if (in_array($action, ['bmc', 'rescue'], true) && ! $dcim) {
            throw new RuntimeException('该服务不支持裸金属维护操作。');
        }
        if ($action === 'rescue') {
            if (! in_array((string) ($payload['system'] ?? ''), ['1', '2'], true)) {
                throw new RuntimeException('请选择有效的救援环境。');
            }
            $data['system'] = (string) $payload['system'];
        }

        $endpoint = match (true) {
            $dcim && $action === 'bmc' => 'dcim_bmc',
            $dcim && $action === 'rescue' => 'dcim_rescue',
            $dcim && $action === 'on' => $this->endpointKey('dcim_on', 'service_action'),
            $dcim && $action === 'off' => $this->endpointKey('dcim_off', 'service_action'),
            $dcim && $action === 'reboot' => $this->endpointKey('dcim_reboot', 'service_action'),
            $dcim && $action === 'reinstall' => $this->endpointKey('dcim_reinstall', 'service_action'),
            $dcim && $action === 'crack_pass' => $this->endpointKey('dcim_crack_pass', 'service_action'),
            default => 'service_action',
        };

        return $this->post($endpoint, $data);
    }

    private function endpointKey(string $preferred, string $fallback): string
    {
        return isset($this->endpoints[$preferred]) && trim((string) $this->endpoints[$preferred]) !== ''
            ? $preferred
            : $fallback;
    }

    private function isDcimHost(string $serviceId): bool
    {
        try {
            $detail = $this->get('services', ['host_id' => $serviceId]);
            $host = (array) data_get($detail, 'data.host_data', []);

            return in_array(strtolower((string) ($host['type'] ?? '')), ['dcim', 'server'], true);
        } catch (RuntimeException) {
            return false;
        }
    }

    private function resolveHostId(string $serviceId): string
    {
        if (is_numeric($serviceId)) {
            return $serviceId;
        }

        $list = $this->get('service_list', ['search' => $serviceId, 'keywords' => $serviceId, 'limit' => 100]);
        foreach ((array) (data_get($list, 'data.list') ?? data_get($list, 'list') ?? []) as $item) {
            if (is_array($item) && (string) ($item['domain'] ?? '') === $serviceId && is_numeric($item['id'] ?? null)) {
                return (string) $item['id'];
            }
        }

        return $serviceId;
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    private function get(string $key, array $query): array
    {
        return $this->payload($this->request()->get($this->endpoint($key), $query)->throw()->json());
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function post(string $key, array $data): array
    {
        return $this->payload($this->request()->asForm()->post($this->endpoint($key), $data)->throw()->json());
    }

    /** @param mixed $value @return array<string, mixed> */
    private function payload(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('魔方上游返回结构无法识别。');
        }
        if ((int) ($value['status'] ?? 200) >= 400) {
            throw new RuntimeException((string) ($value['msg'] ?? '魔方上游操作失败。'));
        }

        return $value;
    }

    private function endpoint(string $key): string
    {
        $endpoint = (string) ($this->endpoints[$key] ?? (['dcim_bmc' => '/dcim/bmc', 'dcim_rescue' => '/dcim/rescue'][$key] ?? ''));
        if ($endpoint === '') {
            throw new RuntimeException('魔方上游未配置 '.$key.' 接口。');
        }

        return $endpoint;
    }

    private function request(): PendingRequest
    {
        if ($this->jwt === null) {
            $this->authenticate();
        }
        $request = Http::withOptions(['cookies' => $this->cookies])
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->jwt)
            ->acceptJson()
            ->connectTimeout(min(5, $this->timeout))
            ->timeout($this->timeout);
        if (! $this->verifySsl) {
            $request->withoutVerifying();
        }

        return $request;
    }

    private function authenticate(): void
    {
        $request = Http::withOptions(['cookies' => $this->cookies])
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->timeout($this->timeout);
        if (! $this->verifySsl) {
            $request->withoutVerifying();
        }
        $payload = $this->payload($request->asForm()->post($this->endpoint('login'), [
            'username' => $this->username,
            'password' => $this->apiKey,
        ])->throw()->json());
        $this->jwt = (string) ($payload['jwt'] ?? '');
        if ($this->jwt === '') {
            throw new RuntimeException('魔方上游鉴权未返回 JWT。');
        }
    }
}
