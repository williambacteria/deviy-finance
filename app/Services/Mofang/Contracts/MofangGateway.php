<?php

namespace App\Services\Mofang\Contracts;

interface MofangGateway
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchProducts(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function payOrder(string $upstreamOrderId): array;

    public function findServiceId(string $invoiceId, string $email = '', string $productId = ''): ?string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function renewService(string $upstreamServiceId, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchService(string $upstreamServiceId): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchServiceMetrics(string $upstreamServiceId): array;

    /** @return array<string, mixed> */
    public function fetchTraffic(string $upstreamServiceId): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function buyTrafficPackage(string $upstreamServiceId, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchVnc(string $upstreamServiceId): array;

    /**
     * @return array<string, mixed>
     */
    /** @param array<string, mixed> $payload */
    public function performServiceAction(string $upstreamServiceId, string $action, array $payload = []): array;
}
