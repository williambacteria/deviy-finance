<?php

namespace App\Services\Qzvps;

use Illuminate\Support\Facades\Cache;
use Throwable;

class QzvpsCatalog
{
    public function __construct(private QzvpsClient $client) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        return Cache::remember('qzvps.catalog.lines', 300, function (): array {
            try {
                return $this->client->lines();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function images(): array
    {
        return Cache::remember('qzvps.catalog.images', 300, function (): array {
            try {
                return $this->client->images();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function imagesForNode(?string $nodeId): array
    {
        $images = $this->images();
        $allowed = (array) config('services.qzvps.os_by_node.'.(string) $nodeId, []);
        if ($allowed === []) {
            return $images;
        }

        return array_values(array_filter($images, fn (array $image): bool => in_array((string) ($image['name'] ?? ''), $allowed, true)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nodes(): array
    {
        return collect((array) config('services.qzvps.nodes', []))
            ->map(fn ($node): array => [
                'id' => (string) ($node['id'] ?? ''),
                'name' => (string) ($node['name'] ?? ''),
                'line_id' => (string) ($node['line_id'] ?? ''),
                'line_name' => (string) ($node['line_name'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
