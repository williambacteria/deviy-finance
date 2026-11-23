<?php

namespace App\Services\Mofang;

use App\Models\UpstreamSupplier;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MofangSupplierTester
{
    /**
     * @return array{count: int, message: string}
     */
    public function test(UpstreamSupplier $supplier): array
    {
        if ($supplier->driver !== 'mofang') {
            return ['count' => 0, 'message' => '当前接口类型仅支持智简魔方。'];
        }

        if ($supplier->base_url === '' || $supplier->username === '' || $supplier->api_key === '') {
            throw new RuntimeException('接口地址、用户名和 API 密钥不能为空。');
        }

        $request = Http::baseUrl(rtrim($supplier->base_url, '/'))
            ->withBasicAuth($supplier->username, $supplier->api_key)
            ->acceptJson()
            ->timeout(20);

        if (! $supplier->verify_ssl) {
            $request->withoutVerifying();
        }

        $response = $request->get('/api/product/list');

        $response->throw();
        $payload = $response->json();
        $items = data_get($payload, 'data.list', []);

        if (! is_array($items)) {
            throw new RuntimeException('上游返回格式无法识别：data.list 不是数组。');
        }

        return [
            'count' => count($items),
            'message' => '连接成功，读取到 '.count($items).' 条上游商品。',
        ];
    }
}
