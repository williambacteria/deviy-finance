<?php

namespace App\Support;

use App\Models\Service;

final class RemoteEndpoint
{
    /** @return array{host: string, port: int, display: string} */
    public static function parse(string $address, ?int $port = null, int $defaultPort = 22): array
    {
        $host = trim($address);
        $resolvedPort = $port && $port <= 65535 ? $port : $defaultPort;
        if (preg_match('/^\[([^\]]+)\]:(\d+)$/D', $host, $matches)) {
            $host = $matches[1];
            $resolvedPort = (int) $matches[2];
        } elseif (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && preg_match('/^([^:\s]+):(\d+)(?::\d+)*$/D', $host, $matches)) {
            $host = $matches[1];
            $resolvedPort = (int) $matches[2];
        } else {
            $host = trim($host, '[]');
        }
        if ($resolvedPort < 1 || $resolvedPort > 65535) {
            $resolvedPort = $defaultPort;
        }
        $displayHost = str_contains($host, ':') ? '['.$host.']' : $host;

        return ['host' => $host, 'port' => $resolvedPort, 'display' => $host === '' ? '' : $displayHost.':'.$resolvedPort];
    }

    /** @return array{host: string, port: int, display: string} */
    public static function forService(Service $service): array
    {
        $windows = str_contains(strtolower((string) $service->os), 'windows');
        $default = $windows ? 3389 : 22;
        $mapping = $service->natMappings->first(fn ($rule) => $rule->internal_port === $default && in_array($rule->protocol, ['tcp', 'tcp+udp', 'all'], true));
        $remote = (array) data_get($service->specs, 'remote', []);
        $localNat=$service->product?->upstream_source==='local'&&$service->product?->type==='nat_vm';
        if ($localNat && ! $mapping) { return ['host'=>'','port'=>$default,'display'=>'']; }
        if ($localNat && $mapping) {
            $host=trim((string)($remote['address']??''));
            if($host==='' || (filter_var($host,FILTER_VALIDATE_IP) && !filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))) { $host=(string)$mapping->external_ip; }
            return self::parse($host,(int)$mapping->external_port,$default);
        }
        $address = trim((string) ($remote['address'] ?? '')) ?: (string) ($mapping?->external_ip ?: $service->nat_ip ?: $service->ip_address);

        return self::parse($address, (int) ($remote['port'] ?? $mapping?->external_port ?? $default), $default);
    }
}
