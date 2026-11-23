<?php

namespace App\Services\Mofang;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MofangCartClient
{
    private CookieJar $cookies;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly bool $verifySsl = true,
    ) {
        $this->cookies = new CookieJar;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createBilling(array $payload): array
    {
        $this->login();

        $productId = (string) ($payload['product_id'] ?? '');
        if ($productId === '') {
            throw new RuntimeException('上游订单缺少商品 ID。');
        }

        $html = $this->get('/cart', ['action' => 'configureproduct', 'pid' => $productId])->body();
        $token = $this->token($html);
        $fields = [
            'token' => $token,
            'pid' => $productId,
            'currencyid' => '1',
            'qty' => (string) max(1, (int) ($payload['quantity'] ?? 1)),
            'billingcycle' => (string) ($payload['billing_cycle'] ?? 'monthly'),
            'host' => 'DV'.Str::upper(Str::random(8)),
            'password' => Str::password(16),
        ];

        preg_match_all('/<input[^>]+name="(configoption\[\d+\])"[^>]*value="([^"]+)"/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (str_contains($match[0], 'checked')) {
                $fields[$match[1]] = $match[2];
            }
        }

        $configured = is_array($payload['configoption'] ?? null) ? $payload['configoption'] : [];
        foreach ($configured as $configId => $value) {
            $id = preg_replace('/\D+/', '', (string) $configId);
            if ($id !== '') {
                $fields['configoption['.$id.']'] = (string) $value;
            }
        }

        $response = $this->post('/cart', ['action' => 'configureproduct', 'pid' => $productId], $fields);
        if ($response->status() >= 400) {
            throw new RuntimeException('上游加购失败：HTTP '.$response->status());
        }

        $cart = $this->get('/cart', ['action' => 'viewcart'])->body();
        $token = $this->token($cart);
        $payment = 'AliPay';
        $balance = $this->accountBalance();
        $cartTotal = $this->cartTotal($cart);
        if ($balance !== null && $cartTotal !== null && $balance >= $cartTotal) {
            $payment = 'Credit';
        }

        $response = $this->post('/cart', ['action' => 'viewcart', 'statuscart' => 'checkout'], [
            'token' => $token,
            'register_or_login' => 'register',
            'i' => '0',
            'qty' => (string) max(1, (int) ($payload['quantity'] ?? 1)),
            'payment' => $payment,
            'promo' => '',
            'terms' => '1',
            'notes' => 'Deviy order '.($payload['order_no'] ?? ''),
        ], false);

        $location = (string) $response->header('Location');
        if (preg_match('/viewbilling\?id=(\d+)/', $location, $matches)) {
            return [
                'order_id' => $matches[1],
                'billing_id' => $matches[1],
                'status' => $payment === 'Credit' ? 'paid' : 'unpaid',
                'payment' => $payment,
                'pay_url' => rtrim($this->baseUrl, '/').'/viewbilling?id='.$matches[1],
            ];
        }

        throw new RuntimeException('上游结算未返回账单地址：HTTP '.$response->status().' '.mb_substr($response->body(), 0, 300));
    }

    /** @return array{fields: array<string, string>, usage: array<string, string>, login_url: ?string, errors: array<int, string>} */
    public function cdnDetails(string $serviceId): array
    {
        if (! ctype_digit($serviceId)) {
            throw new RuntimeException('CDN 上游实例 ID 不合法。');
        }
        $this->login();
        $result = ['fields' => [], 'usage' => [], 'login_url' => null, 'whitelist' => [], 'addons' => [], 'errors' => []];
        foreach (['clientinfo', 'usage', 'whitelist', 'addons'] as $module) {
            $response = $this->get('/provision/custom/content', ['id' => $serviceId, 'key' => $module]);
            if (! $response->successful()) {
                $result['errors'][] = $module;
                continue;
            }
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->body());
            $xpath = new \DOMXPath($dom);
            $labelClass = $module === 'clientinfo' ? 'cdnfly-label' : 'cdnfly-u-label';
            $valueClass = $module === 'clientinfo' ? 'cdnfly-value' : 'cdnfly-u-value';
            foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$labelClass.' ")]') as $label) {
                $value = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " '.$valueClass.' ")]', $label->parentNode)->item(0);
                if ($value) {
                    $result[$module === 'clientinfo' ? 'fields' : 'usage'][trim($label->textContent)] = trim($value->textContent);
                }
            }
            if ($module === 'whitelist') {
                foreach ($xpath->query('//tbody[@id="cdnflyWlRecords"]/tr') as $row) {
                    $cells = $xpath->query('./td', $row);
                    if ($cells->length === 5) {
                        $result['whitelist'][] = array_map(fn ($cell): string => trim($cell->textContent), iterator_to_array($cells));
                    }
                }
            }
            if ($module === 'addons') {
                $result['addons']['available'] = true;
            }
            if ($module === 'clientinfo') {
                foreach ($dom->getElementsByTagName('a') as $anchor) {
                    $url = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (trim($anchor->textContent) === '一键登录 CDN 面板'
                        && parse_url($url, PHP_URL_SCHEME) === 'https'
                        && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['cdnuser.idcdun.net'], true)) {
                        $result['login_url'] = $url;
                    }
                }
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function submitCdnWhitelist(string $serviceId, string $domain): array
    {
        if (! ctype_digit($serviceId) || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($domain, '.')) {
            throw new RuntimeException('请填写合法的完整域名。');
        }
        $this->login();
        $module = $this->get('/provision/custom/content', ['id' => $serviceId, 'key' => 'whitelist'])->throw()->body();
        if (! preg_match('/cdnflyCsrf\s*=\s*[\'"]([a-f0-9]{32,})[\'"]/i', $module, $matches)) {
            throw new RuntimeException('上游未提供白名单请求令牌，请刷新后重试。');
        }
        $response = $this->request()->asForm()->post('/provision/custom/'.$serviceId, ['func' => 'submit_whitelist', 'domain' => $domain, '_csrf' => $matches[1]])->throw()->json();
        if (! is_array($response) || ! in_array((string) ($response['status'] ?? ''), ['200', 'success'], true)) {
            throw new RuntimeException(mb_substr(strip_tags((string) ($response['msg'] ?? '上游白名单提交失败。')), 0, 300));
        }

        return $response;
    }

    private function login(): void
    {
        if ($this->password === '') {
            throw new RuntimeException('上游未配置登录密码，无法使用购物车下单。');
        }

        $html = $this->get('/login')->body();
        $token = $this->token($html);
        $password = openssl_encrypt(
            $this->password,
            'aes-128-cbc',
            'idcsmart.finance',
            OPENSSL_RAW_DATA,
            '9311019310287172',
        );
        $password = base64_encode((string) $password);

        $response = $this->post('/login', ['action' => 'phone'], [
            'token' => $token,
            'phone' => $this->username,
            'password' => $password,
        ], false);

        if ($response->status() !== 302 || ! str_contains((string) $response->header('Location'), 'clientarea')) {
            throw new RuntimeException('上游账号登录失败，请检查账号或密码。');
        }
    }

    private function request()
    {
        $request = Http::withOptions(['cookies' => $this->cookies])
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders(['Accept' => 'text/html'])
            ->timeout(25);

        if (! $this->verifySsl) {
            $request->withoutVerifying();
        }

        return $request;
    }

    private function get(string $path, array $query = [])
    {
        return $this->request()->get($path, $query);
    }

    private function post(string $path, array $query, array $data, bool $follow = true)
    {
        return $this->request()
            ->withOptions(['allow_redirects' => $follow])
            ->asForm()
            ->post($path.'?'.http_build_query($query), $data);
    }

    private function token(string $html): string
    {
        if (! preg_match('/name="token"\s+value="([^"]+)"/i', $html, $matches)) {
            throw new RuntimeException('上游页面缺少请求令牌。');
        }

        return $matches[1];
    }

    private function accountBalance(): ?float
    {
        try {
            $html = $this->get('/clientarea')->body();
        } catch (Throwable) {
            return null;
        }

        if (preg_match('/账户余额[\s\S]{0,300}?([0-9]+(?:\.[0-9]+)?)\s*元/u', $html, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    private function cartTotal(string $html): ?float
    {
        if (preg_match('/总计[\s\S]{0,200}?(?:￥|¥)\s*([0-9]+(?:\.[0-9]+)?)/u', $html, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }
}
