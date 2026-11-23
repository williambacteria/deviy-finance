<?php

namespace App\Services\Notifications\Contracts;

interface SmsGateway
{
    public function send(string $phone, string $message): void;
}
