<?php

namespace App\Services\Pterodactyl;

use App\Models\Plugin;
use RuntimeException;

class PterodactylSettings
{
    public const CODE = 'pterodactyl';

    public static function plugin(): Plugin
    {
        return Plugin::query()->firstOrCreate(
            ['code' => self::CODE],
            [
                'name' => '翼龙面板商品对接',
                'type' => 'upstream',
                'version' => '1.0.0',
                'description' => '连接翼龙面板（Pterodactyl），同步节点与 Eggs，导入游戏服务器商品并自动开通。',
                'is_enabled' => true,
                'config' => [
                    'base_url' => 'https://pl.deviy.cn',
                    'api_key' => '',
                    'client_api_key' => '',
                ],
            ],
        );
    }

    /** @return array<string, mixed> */
    public static function configuration(): array
    {
        $plugin = self::plugin();
        $config = is_array($plugin->config) ? $plugin->config : [];

        return array_merge([
            'base_url' => 'https://pl.deviy.cn',
            'api_key' => '',
            'client_api_key' => '',
        ], $config);
    }

    public static function client(): PterodactylClient
    {
        $plugin = self::plugin();
        if (! $plugin->is_enabled) {
            throw new RuntimeException('翼龙面板插件未启用。');
        }

        $config = self::configuration();
        if (empty($config['api_key'])) {
            throw new RuntimeException('翼龙面板 Application API Key 未配置。');
        }

        return new PterodactylClient(
            (string) $config['base_url'],
            (string) $config['api_key'],
            $config['client_api_key'] ? (string) $config['client_api_key'] : null,
        );
    }
}
