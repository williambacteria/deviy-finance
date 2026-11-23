<?php

namespace App\Support;

use App\Models\Setting;

final class HomepageFriendLinks
{
    public const KEY = 'homepage_friend_links';

    /** @return array<string, array{name: string, url: string, description: string, logo: ?string, position: int, enabled: bool}> */
    public static function ordered(?string $value): array
    {
        $links = json_decode($value ?? '{}', true);
        if (! is_array($links)) {
            return [];
        }
        uasort($links, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $links;
    }

    public static function forHomepage(): array
    {
        return array_filter(self::ordered(Setting::value(self::KEY)), static fn (array $link): bool => $link['enabled']);
    }
}
