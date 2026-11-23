<?php

namespace App\Services\Notifications;

use App\Services\Notifications\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $url,
        private readonly string $token,
        private readonly string $sign,
    ) {}

    public function send(string $phone, string $message): void
    {
        if ($this->url === '') {
            throw new RuntimeException('短信网关地址未配置。');
        }

        Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->post($this->url, [
                'phone' => $phone,
                'message' => $message,
                'sign' => $this->sign,
            ])
            ->throw();
    }
}
