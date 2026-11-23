<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 魔方财务上游
    |--------------------------------------------------------------------------
    |
    | driver=mock 时使用内置演示商品，便于开发；driver=http 时通过上游
    | API 拉取。真实环境请先用测试账号确认上游版本、接口路径与签名方式。
    |
    */
    'driver' => env('MOFANG_DRIVER', 'mock'),

    'base_url' => env('MOFANG_BASE_URL', 'https://www.example.com'),

    'username' => env('MOFANG_USERNAME', ''),

    'api_key' => env('MOFANG_API_KEY', ''),

    'timeout' => (int) env('MOFANG_TIMEOUT', 15),

    'default_markup_percent' => (float) env('MOFANG_MARKUP_PERCENT', 20),

    'endpoints' => [
        'products' => env('MOFANG_PRODUCTS_ENDPOINT', '/api/product/list'),
        'login' => env('MOFANG_LOGIN_ENDPOINT', '/zjmf_api_login'),
        'product_config' => env('MOFANG_PRODUCT_CONFIG_ENDPOINT', '/cart/get_product_config'),
        'cart_add' => env('MOFANG_CART_ADD_ENDPOINT', '/cart/add_to_shop'),
        'cart_settle' => env('MOFANG_CART_SETTLE_ENDPOINT', '/cart/settle'),
        'apply_credit' => env('MOFANG_APPLY_CREDIT_ENDPOINT', '/apply_credit'),
        'orders' => env('MOFANG_ORDERS_ENDPOINT', '/cart/settle'),
        'pay_order' => env('MOFANG_PAY_ORDER_ENDPOINT', '/apply_credit'),
        'renewals' => env('MOFANG_RENEWALS_ENDPOINT', '/host/renew'),
        'services' => env('MOFANG_SERVICES_ENDPOINT', '/host/header'),
        'service_list' => env('MOFANG_SERVICE_LIST_ENDPOINT', '/host/list'),
        'metrics' => env('MOFANG_METRICS_ENDPOINT', '/provision/chart/{id}'),
        'dcim_on' => env('MOFANG_DCIM_ON_ENDPOINT', '/dcim/on'),
        'dcim_off' => env('MOFANG_DCIM_OFF_ENDPOINT', '/dcim/off'),
        'dcim_reboot' => env('MOFANG_DCIM_REBOOT_ENDPOINT', '/dcim/reboot'),
        'dcim_reinstall' => env('MOFANG_DCIM_REINSTALL_ENDPOINT', '/dcim/reinstall'),
        'dcim_vnc' => env('MOFANG_DCIM_VNC_ENDPOINT', '/dcim/novnc'),
        'dcim_crack_pass' => env('MOFANG_DCIM_CRACK_PASS_ENDPOINT', '/dcim/crack_pass'),
        'traffic' => env('MOFANG_TRAFFIC_ENDPOINT', '/dcim/traffic_usage'),
        'traffic_packages' => env('MOFANG_TRAFFIC_PACKAGES_ENDPOINT', '/dcim/traffic_package'),
        'buy_traffic' => env('MOFANG_BUY_TRAFFIC_ENDPOINT', '/dcim/buy_flow_packet'),
        'service_action' => env('MOFANG_SERVICE_ACTION_ENDPOINT', '/provision/default'),
    ],
];
