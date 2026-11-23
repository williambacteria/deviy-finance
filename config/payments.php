<?php

return [
    'alipay' => [
        'enabled' => env('ALIPAY_ENABLED', false),
        'app_id' => env('ALIPAY_APP_ID', ''),
        'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
        'public_key' => env('ALIPAY_PUBLIC_KEY', ''),
        'gateway' => env('ALIPAY_GATEWAY', 'https://openapi.alipay.com/gateway.do'),
        'notify_url' => env('ALIPAY_NOTIFY_URL', ''),
        'return_url' => env('ALIPAY_RETURN_URL', ''),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ALIPAY_ALLOWED_IPS', ''))))),
    ],

    'wechat' => [
        'enabled' => env('WECHAT_PAY_ENABLED', false),
        'app_id' => env('WECHAT_PAY_APP_ID', ''),
        'mch_id' => env('WECHAT_PAY_MCH_ID', ''),
        'serial_no' => env('WECHAT_PAY_SERIAL_NO', ''),
        'private_key' => env('WECHAT_PAY_PRIVATE_KEY', ''),
        'platform_public_key' => env('WECHAT_PAY_PLATFORM_PUBLIC_KEY', ''),
        'platform_key_id' => env('WECHAT_PAY_PLATFORM_KEY_ID', ''),
        'api_v3_key' => env('WECHAT_PAY_API_V3_KEY', ''),
        'gateway' => env('WECHAT_PAY_GATEWAY', 'https://api.mch.weixin.qq.com'),
        'notify_url' => env('WECHAT_PAY_NOTIFY_URL', ''),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('WECHAT_PAY_ALLOWED_IPS', ''))))),
    ],
];
