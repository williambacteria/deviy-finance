<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AlipayGateway
{
    public function isConfigured(): bool
    {
        return (bool) config('payments.alipay.enabled')
            && $this->appId() !== ''
            && $this->privateKey() !== ''
            && $this->publicKey() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function createQrPayment(Payment $payment, string $subject = '账户充值'): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('支付宝支付未配置。');
        }

        $params = $this->baseParams('alipay.trade.precreate', [
            'out_trade_no' => $payment->payment_no,
            'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'subject' => $subject,
            'timeout_express' => '15m',
        ]);
        $params['sign'] = $this->sign($this->canonical($params));

        $response = Http::asForm()->timeout(15)->post((string) config('payments.alipay.gateway'), $params);
        if (! $response->successful()) {
            throw new RuntimeException('支付宝创建扫码支付失败：HTTP '.$response->status());
        }

        $body = (array) ($response->json('alipay_trade_precreate_response') ?? $response->json());
        if ((string) ($body['code'] ?? '') !== '10000' || empty($body['qr_code'])) {
            return [
                'qr_code' => $this->paymentUrl($payment, $subject),
                'out_trade_no' => $payment->payment_no,
                'fallback' => true,
            ];
        }

        return [
            'qr_code' => (string) $body['qr_code'],
            'out_trade_no' => (string) ($body['out_trade_no'] ?? $payment->payment_no),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function query(string $paymentNo): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('支付宝支付未配置。');
        }

        $params = $this->baseParams('alipay.trade.query', [
            'out_trade_no' => $paymentNo,
        ]);
        $params['sign'] = $this->sign($this->canonical($params));

        $response = Http::asForm()->connectTimeout(3)->timeout(8)->post((string) config('payments.alipay.gateway'), $params);
        if (! $response->successful()) {
            throw new RuntimeException('支付宝查单失败：HTTP '.$response->status());
        }

        return $this->verifiedQueryResponse($response->body());
    }


    public function closeUnpaid(string $paymentNo): void
    {
        $params=$this->baseParams('alipay.trade.close',['out_trade_no'=>$paymentNo]);
        $params['sign']=$this->sign($this->canonical($params));
        $response=Http::asForm()->connectTimeout(5)->timeout(12)->post((string)config('payments.alipay.gateway'),$params);
        if(!$response->successful()){throw new RuntimeException('支付宝关单请求未完成。');}
        $result=$this->verifiedQueryResponse($response->body(),'alipay_trade_close_response');
        if(($result['code']??'')!=='10000'&&!in_array($result['sub_code']??'',['ACQ.TRADE_NOT_EXIST','ACQ.TRADE_STATUS_ERROR'],true)){
            throw new RuntimeException('支付宝关单状态未确认。');
        }
    }

    /**
     * @param  array<string, mixed>  $biz
     * @return array<string, string>
     */
    private function baseParams(string $method, array $biz): array
    {
        $params = [
            'app_id' => $this->appId(),
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => (string) config('payments.alipay.notify_url'),
            'return_url' => (string) config('payments.alipay.return_url'),
            'biz_content' => json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return array_filter($params, fn ($value): bool => $value !== '');
    }

    public function paymentUrl(Payment $payment, string $subject = '账户充值'): string
    {
        return (string) config('payments.alipay.gateway').'?'.http_build_query($this->paymentParams($payment, $subject));
    }

    /**
     * @return array<string, string>
     */
    public function paymentParams(Payment $payment, string $subject = '账户充值'): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('支付宝支付未配置。');
        }

        $params = [
            'app_id' => $this->appId(),
            'method' => 'alipay.trade.page.pay',
            'format' => 'json',
            'charset' => 'UTF-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'alipay_sdk' => 'alipay-sdk-php-20161101',
            'notify_url' => (string) config('payments.alipay.notify_url'),
            'return_url' => (string) config('payments.alipay.return_url'),
            'biz_content' => json_encode([
                'out_trade_no' => $payment->payment_no,
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
                'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
                'subject' => $subject,
                'qr_pay_mode' => '4',
                'qrcode_width' => '220',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $params = array_filter($params, fn ($value): bool => $value !== '');
        $params['sign'] = $this->sign($this->canonical($params));

        return $params;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        $signature = (string) ($payload['sign'] ?? '');
        if ($signature === '' || (string) ($payload['app_id'] ?? '') !== $this->appId()) {
            return false;
        }

        unset($payload['sign'], $payload['sign_type']);
        $content = $this->canonical($payload);
        $key = openssl_pkey_get_public($this->publicKey());
        if ($key === false) {
            throw new RuntimeException('支付宝公钥无效。');
        }

        return openssl_verify($content, base64_decode($signature, true) ?: '', $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonical(array $payload): string
    {
        ksort($payload);
        $parts = [];
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key.'='.$value;
        }

        return implode('&', $parts);
    }

    private function sign(string $content): string
    {
        $key = openssl_pkey_get_private($this->privateKey());
        if ($key === false) {
            throw new RuntimeException('支付宝私钥无效。');
        }

        if (! openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('支付宝签名失败。');
        }

        return base64_encode($signature);
    }

    /**
     * Verify the original JSON response bytes, as required by Alipay RSA2.
     *
     * @return array<string, mixed>
     */
    private function verifiedQueryResponse(string $json, string $node = 'alipay_trade_query_response'): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $body = $decoded[$node] ?? null;
        if (! is_array($body)) {
            throw new RuntimeException('支付宝查单响应格式无效。');
        }

        if ((string) ($body['code'] ?? '') !== '10000') {
            return $body;
        }

        $signature = base64_decode((string) ($decoded['sign'] ?? ''), true);
        $key = openssl_pkey_get_public($this->publicKey());
        if ($signature === false || $signature === '' || $key === false) {
            throw new RuntimeException('支付宝查单响应缺少有效签名。');
        }

        if (! preg_match('/"'.preg_quote($node, '/').'"\s*:\s*/', $json, $match, PREG_OFFSET_CAPTURE)) {
            throw new RuntimeException('支付宝查单响应节点缺失。');
        }
        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 0;
        $quoted = false;
        $escaped = false;
        $raw = '';
        for ($index = $start, $length = strlen($json); $index < $length; $index++) {
            $char = $json[$index];
            if ($quoted) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === chr(92)) {
                    $escaped = true;
                } elseif ($char === '"') {
                    $quoted = false;
                }
            } elseif ($char === '"') {
                $quoted = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                $raw = substr($json, $start, $index - $start + 1);
                break;
            }
        }
        if ($raw === '' || openssl_verify($raw, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('支付宝查单响应验签失败，未执行入账。');
        }

        return $body;
    }

    private function appId(): string
    {
        return (string) config('payments.alipay.app_id');
    }

    private function privateKey(): string
    {
        return $this->readKey((string) config('payments.alipay.private_key'), true);
    }

    private function publicKey(): string
    {
        return $this->readKey((string) config('payments.alipay.public_key'), false);
    }

    private function readKey(string $value, bool $private): string
    {
        if ($value === '') {
            return '';
        }

        if (str_contains($value, 'BEGIN')) {
            return $value;
        }

        if (is_file($value)) {
            return (string) file_get_contents($value);
        }

        $body = chunk_split(preg_replace('/\s+/', '', $value) ?? '', 64, "\n");
        $label = $private ? 'RSA PRIVATE KEY' : 'PUBLIC KEY';

        return "-----BEGIN {$label}-----\n{$body}-----END {$label}-----";
    }
}
