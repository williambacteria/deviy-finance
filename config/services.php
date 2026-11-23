<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'url' => env('SMS_HTTP_URL', ''),
        'token' => env('SMS_HTTP_TOKEN', ''),
        'sign' => env('SMS_SIGN', 'Deviy'),
    ],

    'aliyun_market' => [
        'appcode' => env('ALIYUN_MARKET_APPCODE'),
        'endpoint' => env('ALIYUN_MARKET_ENDPOINT', 'https://dfphone3.market.alicloudapi.com/verify_id_name_phone'),
    ],

    'qzvps' => [
        'url' => env('QZVPS_URL', 'https://vps.deviy.cn'),
        'api_key' => env('QZVPS_API_KEY', ''),
        'timeout' => env('QZVPS_TIMEOUT', 30),
        'verify_tls' => env('QZVPS_VERIFY_TLS', false),
        'nodes' => [
            ['id' => '1', 'name' => 'Platinum 8124M', 'line_id' => '1', 'line_name' => '绍兴电信 Hyper-V'],
            ['id' => '2', 'name' => 'E5 2667v2', 'line_id' => '1', 'line_name' => '绍兴电信 Hyper-V'],
            ['id' => '4', 'name' => 'R7-5800X-G1', 'line_id' => '1', 'line_name' => '绍兴电信 Hyper-V'],
            ['id' => '7', 'name' => 'R9-9950X-ZJ', 'line_id' => '3', 'line_name' => '镇江 BGP-PVE'],
        ],
        'os_by_node' => [
            '1' => [
                'WindowsServer2022',
                'Debian11.7',
                'Centos7.6',
                'WindowsServer2016',
                'WindowsServer2008',
                'Ubuntu18.04',
                'Ubuntu22.04',
            ],
        ],
    ],

];
