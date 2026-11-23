<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WechatPayGateway
{
    public function isConfigured(): bool
    {
        return (bool) config('payments.wechat.enabled')
            && (string) config('payments.wechat.mch_id') !== ''
            && (string) config('payments.wechat.app_id') !== ''
            && (string) config('payments.wechat.private_key') !== ''
            && (string) config('payments.wechat.serial_no') !== ''
            && (string) config('payments.wechat.platform_key_id') !== ''
            && (string) config('payments.wechat.platform_public_key') !== ''
            && filter_var((string) config('payments.wechat.notify_url'), FILTER_VALIDATE_URL) !== false
            && strlen((string) config('payments.wechat.api_v3_key')) === 32;
    }

    /**
     * @return array<string, mixed>
     */
    public function createNative(Payment $payment, string $description = '账户充值'): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('微信支付未配置。');
        }

        $body = json_encode([
            'appid' => (string) config('payments.wechat.app_id'),
            'mchid' => (string) config('payments.wechat.mch_id'),
            'description' => $description,
            'out_trade_no' => $payment->payment_no,
            'notify_url' => (string) config('payments.wechat.notify_url'),
            'time_expire' => now()->addMinutes(15)->toRfc3339String(),
            'amount' => [
                'total' => (int) bcmul((string) $payment->amount, '100', 0),
                'currency' => 'CNY',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $path = '/v3/pay/transactions/native';

        $response = Http::withHeaders([
            'Authorization' => $this->authorization('POST', $path, $body),
            'Accept' => 'application/json',
            'Wechatpay-Serial' => (string) config('payments.wechat.platform_key_id'),
            'Content-Type' => 'application/json',
        ])->withBody($body, 'application/json')->timeout(10)->post((string) config('payments.wechat.gateway').$path);

        if (! $response->successful()) {
            throw new RuntimeException('微信下单失败：HTTP '.$response->status().' '.$response->body());
        }

        $this->verifySignature((string) $response->header('Wechatpay-Timestamp'), (string) $response->header('Wechatpay-Nonce'), (string) $response->header('Wechatpay-Signature'), (string) $response->header('Wechatpay-Serial'), $response->body());
        $data = $response->json();
        if (! is_array($data) || ! is_string($data['code_url'] ?? null) || ! str_starts_with($data['code_url'], 'weixin://wxpay/bizpayurl?')) {
            throw new RuntimeException('微信下单未返回 code_url。');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(string $paymentNo): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('微信支付未配置。');
        }

        $path = '/v3/pay/transactions/out-trade-no/'.rawurlencode($paymentNo).'?mchid='.rawurlencode((string) config('payments.wechat.mch_id'));

        $response = Http::withHeaders([
            'Authorization' => $this->authorization('GET', $path, ''),
            'Accept' => 'application/json',
            'Wechatpay-Serial' => (string) config('payments.wechat.platform_key_id'),
        ])->timeout(10)->get((string) config('payments.wechat.gateway').$path);

        if (! $response->successful()) {
            if ($response->status() === 404 && $response->json('code') === 'ORDER_NOT_EXIST') {
                $this->verifySignature((string) $response->header('Wechatpay-Timestamp'), (string) $response->header('Wechatpay-Nonce'), (string) $response->header('Wechatpay-Signature'), (string) $response->header('Wechatpay-Serial'), $response->body());
                return ['trade_state' => 'NOT_FOUND', 'out_trade_no' => $paymentNo];
            }
            throw new RuntimeException('微信查单失败：HTTP '.$response->status());
        }

        $this->verifySignature((string) $response->header('Wechatpay-Timestamp'), (string) $response->header('Wechatpay-Nonce'), (string) $response->header('Wechatpay-Signature'), (string) $response->header('Wechatpay-Serial'), $response->body());
        return (array) $response->json();
    }


    public function closeUnpaid(string $paymentNo): void
    {
        $path = '/v3/pay/transactions/out-trade-no/'.rawurlencode($paymentNo).'/close';
        $body = json_encode(['mchid' => (string) config('payments.wechat.mch_id')], JSON_UNESCAPED_SLASHES);
        $response = Http::withHeaders([
            'Authorization' => $this->authorization('POST', $path, $body),
            'Accept' => 'application/json',
            'Wechatpay-Serial' => (string) config('payments.wechat.platform_key_id'),
        ])->withBody($body, 'application/json')->connectTimeout(5)->timeout(15)->post((string) config('payments.wechat.gateway').$path);
        if ($response->status() !== 204 && ! in_array($response->json('code'), ['ORDER_CLOSED', 'ORDER_PAID'], true)) {
            throw new RuntimeException('原微信订单关闭结果未确认，请稍后重试。');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyCallback(Request $request): array
    {
        $timestamp = (string) $request->header('Wechatpay-Timestamp');
        $nonce = (string) $request->header('Wechatpay-Nonce');
        $signature = (string) $request->header('Wechatpay-Signature');
        $body = $request->getContent();

        $this->verifySignature($timestamp, $nonce, $signature, (string) $request->header('Wechatpay-Serial'), $body);

        $payload = json_decode($body, true);
        if (! is_array($payload) || ! isset($payload['resource'])) {
            throw new RuntimeException('微信回调数据结构无效。');
        }

        return [
            'event_type' => (string) ($payload['event_type'] ?? ''),
            'resource' => $this->decryptResource((array) $payload['resource']),
        ];
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    private function decryptResource(array $resource): array
    {
        $ciphertext = base64_decode((string) ($resource['ciphertext'] ?? ''), true);
        $nonce = (string) ($resource['nonce'] ?? '');
        $aad = (string) ($resource['associated_data'] ?? '');
        if ($ciphertext === false || strlen($ciphertext) <= 16 || $nonce === '') {
            throw new RuntimeException('微信回调密文无效。');
        }

        $tag = substr($ciphertext, -16);
        $cipher = substr($ciphertext, 0, -16);
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            (string) config('payments.wechat.api_v3_key'),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
        );
        if ($plain === false) {
            throw new RuntimeException('微信回调解密失败。');
        }

        $data = json_decode($plain, true);
        if (! is_array($data)) {
            throw new RuntimeException('微信回调解密内容无效。');
        }

        return $data;
    }



    public function validateTransaction(Payment $payment, array $resource): bool
    {
        if (($resource['trade_state'] ?? '') !== 'SUCCESS') {
            return false;
        }
        $expected = (int) bcmul((string) $payment->amount, '100', 0);
        if ($payment->gateway !== 'wechat'
            || (string) ($resource['mchid'] ?? '') !== (string) config('payments.wechat.mch_id')
            || (string) ($resource['appid'] ?? '') !== (string) config('payments.wechat.app_id')
            || (string) ($resource['out_trade_no'] ?? '') !== $payment->payment_no
            || (string) ($resource['amount']['currency'] ?? '') !== 'CNY'
            || ! is_int($resource['amount']['total'] ?? null) || $resource['amount']['total'] !== $expected
            || ! is_string($resource['transaction_id'] ?? null) || $resource['transaction_id'] === '') {
            throw new RuntimeException('微信交易的商户、订单、币种或金额与本站充值单不匹配。');
        }
        if (Payment::query()->where('gateway', 'wechat')->where('transaction_id', $resource['transaction_id'])->whereKeyNot($payment->id)->exists()) {
            throw new RuntimeException('该微信交易号已关联其他支付单。');
        }

        return true;
    }

    private function verifySignature(string $timestamp, string $nonce, string $signature, string $serial, string $body): void
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || $nonce === '' || $signature === '') {
            throw new RuntimeException('微信支付签名头缺失或时间无效。');
        }
        $key = @openssl_pkey_get_public($this->verificationPem($serial));
        $bytes = base64_decode($signature, true);
        if ($key === false || $bytes === false || openssl_verify($timestamp."\n".$nonce."\n".$body."\n", $bytes, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('微信支付签名验证失败。');
        }
    }


    private function verificationPem(string $serial): string
    {
        $expected = (string) config('payments.wechat.platform_key_id');
        if ($expected !== '' && hash_equals($expected, $serial)) {
            return $this->readKey((string) config('payments.wechat.platform_public_key'));
        }
        if (preg_match('/^[A-Fa-f0-9]{20,64}$/D', $serial) !== 1) {
            throw new RuntimeException('微信支付公钥 ID 不匹配。');
        }
        $cacheKey = 'payments:wechat:certificates:'.hash('sha256', (string) config('payments.wechat.mch_id').'|'.(string) config('payments.wechat.api_v3_key'));
        $certificates = (array) \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        $certificate = $certificates[strtoupper($serial)] ?? null;
        if (! is_array($certificate) || (int) $certificate['expires'] <= time()) {
            if (! \Illuminate\Support\Facades\Cache::add($cacheKey.':refresh', true, 30)) {
                throw new RuntimeException('微信平台证书正在刷新，请稍后重试。');
            }
            $certificates = $this->platformCertificates();
            \Illuminate\Support\Facades\Cache::put($cacheKey, $certificates, now()->addHours(6));
            $certificate = $certificates[strtoupper($serial)] ?? null;
        }
        if (! is_array($certificate) || (int) $certificate['expires'] <= time()) {
            throw new RuntimeException('微信支付平台证书不存在或已过期。');
        }

        return (string) $certificate['pem'];
    }

    private function platformCertificates(): array
    {
        $path = '/v3/certificates';
        $response = Http::withHeaders([
            'Authorization' => $this->authorization('GET', $path, ''),
            'Accept' => 'application/json',
        ])->connectTimeout(5)->timeout(15)->get('https://api.mch.weixin.qq.com'.$path);
        if (! $response->successful()) {
            throw new RuntimeException('微信支付平台证书获取失败：HTTP '.$response->status());
        }
        $certificates = [];
        foreach ((array) $response->json('data', []) as $item) {
            $resource = (array) ($item['encrypt_certificate'] ?? []);
            $ciphertext = base64_decode((string) ($resource['ciphertext'] ?? ''), true);
            if ($ciphertext === false || strlen($ciphertext) <= 16 || ($resource['algorithm'] ?? '') !== 'AEAD_AES_256_GCM') {
                continue;
            }
            $pem = openssl_decrypt(substr($ciphertext, 0, -16), 'aes-256-gcm', (string) config('payments.wechat.api_v3_key'), OPENSSL_RAW_DATA, (string) ($resource['nonce'] ?? ''), substr($ciphertext, -16), (string) ($resource['associated_data'] ?? ''));
            $info = $pem !== false ? @openssl_x509_parse($pem) : false;
            $serial = strtoupper((string) ($item['serial_no'] ?? ''));
            if (! $info || $serial === '' || strtoupper((string) ($info['serialNumberHex'] ?? '')) !== $serial || $info['validFrom_time_t'] > time() || $info['validTo_time_t'] <= time()) {
                continue;
            }
            $certificates[$serial] = ['pem' => $pem, 'expires' => $info['validTo_time_t']];
        }
        $responseSerial = (string) $response->header('Wechatpay-Serial');
        $pem = $certificates[strtoupper($responseSerial)]['pem'] ?? null;
        if ($pem === null && hash_equals((string) config('payments.wechat.platform_key_id'), $responseSerial)) {
            $pem = $this->readKey((string) config('payments.wechat.platform_public_key'));
        }
        $timestamp = (string) $response->header('Wechatpay-Timestamp');
        $nonce = (string) $response->header('Wechatpay-Nonce');
        $signature = base64_decode((string) $response->header('Wechatpay-Signature'), true);
        $key = $pem !== null ? @openssl_pkey_get_public($pem) : false;
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || $nonce === '' || $key === false || $signature === false || openssl_verify($timestamp."\n".$nonce."\n".$response->body()."\n", $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('微信支付平台证书响应无法通过验签。');
        }

        return $certificates;
    }

    private function authorization(string $method, string $path, string $body): string
    {
        $timestamp = (string) time();
        $nonce = Str::random(32);
        $message = $method."\n".$path."\n".$timestamp."\n".$nonce."\n".$body."\n";
        $privateKey = openssl_pkey_get_private($this->readKey((string) config('payments.wechat.private_key')));
        if ($privateKey === false || ! openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('微信支付签名失败。');
        }

        return 'WECHATPAY2-SHA256-RSA2048 '
            .'mchid="'.config('payments.wechat.mch_id').'",'
            .'nonce_str="'.$nonce.'",'
            .'timestamp="'.$timestamp.'",'
            .'serial_no="'.config('payments.wechat.serial_no').'",'
            .'signature="'.base64_encode($signature).'"';
    }

    private function readKey(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_contains($value, 'BEGIN') || ! is_file($value) ? $value : (string) file_get_contents($value);
    }
}
