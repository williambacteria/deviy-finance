<?php

namespace App\Support;

use App\Models\Product;
use InvalidArgumentException;

final class ProductOptions
{
    public const OS_OPTIONS = [
        'win2k22' => 'Windows Server 2022',
        'win2019' => 'Windows Server 2019',
        'ubuntu2404' => 'Ubuntu 24.04 LTS',
        'ubuntu2204' => 'Ubuntu 22.04 LTS',
        'debian12' => 'Debian 12',
        'debian11' => 'Debian 11',
        'centos9' => 'CentOS Stream 9',
        'rocky9' => 'Rocky Linux 9',
        'l26' => 'Linux / 其他',
    ];

    /**
     * @var array<string, array{label: string, unit: string, type: string}>
     */
    public const DIMENSIONS = [
        'cpu' => ['label' => 'CPU 核心', 'unit' => '核', 'type' => 'int'],
        'memory_gb' => ['label' => '内存', 'unit' => 'GB', 'type' => 'int'],
        'disk_gb' => ['label' => '系统盘', 'unit' => 'GB', 'type' => 'int'],
        'data_disk_gb' => ['label' => '数据盘', 'unit' => 'GB', 'type' => 'int'],
        'upload_mbps' => ['label' => '上行带宽', 'unit' => 'Mbps', 'type' => 'int'],
        'download_mbps' => ['label' => '下行带宽', 'unit' => 'Mbps', 'type' => 'int'],
        'nat_ports' => ['label' => 'NAT 转发端口数量', 'unit' => '个', 'type' => 'int'],
        'ostype' => ['label' => '操作系统', 'unit' => '', 'type' => 'string'],
    ];

    /**
     * @return array<string, array<int, array{value: int|string, label: string, price: float}>>
     */
    public static function options(Product $product): array
    {
        $metadata = $product->metadata ?? [];
        $stored = is_array($metadata['options'] ?? null) ? $metadata['options'] : [];
        $specs = is_array($metadata['specs'] ?? null) ? $metadata['specs'] : [];
        $ostype = (string) ($metadata['controller']['ostype'] ?? 'l26');

        $defaults = [
            'cpu' => (int) ($specs['cpu'] ?? 2),
            'memory_gb' => (int) ($specs['memory_gb'] ?? 4),
            'disk_gb' => (int) ($specs['disk_gb'] ?? 40),
            'data_disk_gb' => (int) ($specs['data_disk_gb'] ?? 0),
            'upload_mbps' => (int) ($specs['upload_mbps'] ?? $specs['bandwidth_mbps'] ?? 10),
            'download_mbps' => (int) ($specs['download_mbps'] ?? $specs['bandwidth_mbps'] ?? 10),
            'nat_ports' => (int) ($specs['nat_ports'] ?? 20),
            'ostype' => $ostype,
        ];

        $result = [];
        foreach (self::DIMENSIONS as $key => $definition) {
            $hasStoredKey = array_key_exists($key, $stored) && is_array($stored[$key]);
            $rows = $hasStoredKey ? $stored[$key] : [];
            $normalized = [];
            foreach ($rows as $row) {
                if (! is_array($row) || ! array_key_exists('value', $row)) {
                    continue;
                }
                $value = $definition['type'] === 'int' ? (int) $row['value'] : (string) $row['value'];
                if ($value === '' || ($definition['type'] === 'int' && $value < 1 && $key !== 'data_disk_gb')) {
                    continue;
                }
                $normalized[(string) $value] = [
                    'value' => $value,
                    'label' => trim((string) ($row['label'] ?? '')) ?: self::label($key, $value),
                    'price' => max(0, round((float) ($row['price'] ?? 0), 2)),
                ];
            }

            if ($normalized === [] && ! $hasStoredKey) {
                $value = $defaults[$key];
                $normalized[(string) $value] = [
                    'value' => $value,
                    'label' => self::label($key, $value),
                    'price' => 0.0,
                ];
            }

            $result[$key] = array_values($normalized);
        }

        return $result;
    }

    /**
     * @return array<string, float>
     */
    public static function cycleDiscounts(Product $product): array
    {
        $metadata = $product->metadata ?? [];
        $stored = is_array($metadata['cycle_discounts'] ?? null) ? $metadata['cycle_discounts'] : [];
        $result = ['monthly' => 1.0];

        foreach (['quarterly', 'semiannually', 'annually'] as $cycle) {
            $result[$cycle] = min(1, max(0.1, (float) ($stored[$cycle] ?? 1)));
        }

        return $result;
    }

    public const RANGE_DIMENSIONS = ['data_disk_gb', 'upload_mbps', 'download_mbps'];

    /**
     * @return array<string, array{hint: string, range: array{enabled: bool, min: float, max: float, step: float, unit_price: float}}>
     */
    public static function settings(Product $product): array
    {
        $metadata = $product->metadata ?? [];
        $stored = is_array($metadata['option_settings'] ?? null) ? $metadata['option_settings'] : [];
        $result = [];

        foreach (self::DIMENSIONS as $key => $definition) {
            $item = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $range = is_array($item['range'] ?? null) ? $item['range'] : [];
            $min = (float) ($range['min'] ?? 0);
            $max = (float) ($range['max'] ?? 0);
            $step = (float) ($range['step'] ?? 1);
            $enabled = in_array($key, self::RANGE_DIMENSIONS, true) && ! empty($range['enabled']);

            $result[$key] = [
                'hint' => array_key_exists('hint', $item) ? trim((string) $item['hint']) : '选择配置，价格实时计算',
                'range' => [
                    'enabled' => $enabled,
                    'min' => max(0, $min),
                    'max' => max($min, $max),
                    'step' => max(0.01, $step),
                    'unit_price' => max(0, (float) ($range['unit_price'] ?? 0)),
                ],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, array{hint: string, range: array{enabled: bool, min: float, max: float, step: float, unit_price: float}}>
     */
    public static function normalizeSettings(array $raw, Product $product): array
    {
        $result = [];
        foreach (self::DIMENSIONS as $key => $definition) {
            $item = is_array($raw[$key] ?? null) ? $raw[$key] : [];
            $min = max(0, (float) ($item['range_min'] ?? 0));
            $max = max(0, (float) ($item['range_max'] ?? 0));
            $step = max(0.01, (float) ($item['range_step'] ?? 1));
            $enabled = in_array($key, self::RANGE_DIMENSIONS, true) && filter_var($item['range_enabled'] ?? false, FILTER_VALIDATE_BOOL);
            if ($enabled && $max <= $min) {
                $max = $min + $step;
            }

            $result[$key] = [
                'hint' => array_key_exists('hint', $item) ? trim((string) $item['hint']) : '选择配置，价格实时计算',
                'range' => [
                    'enabled' => $enabled,
                    'min' => $min,
                    'max' => $max,
                    'step' => $step,
                    'unit_price' => max(0, (float) ($item['range_unit_price'] ?? 0)),
                ],
            ];
        }

        return $result;
    }

    /**
     * @return array<string, int|string>
     */
    public static function defaults(Product $product): array
    {
        return array_map(
            fn (array $rows) => $rows[0]['value'],
            self::options($product),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{specs: array<string, int>, ostype: string, selection: array<string, mixed>, monthly_addon: float}
     */
    public static function resolve(Product $product, array $input): array
    {
        $options = self::options($product);
        $metadata = $product->metadata ?? [];
        $specs = is_array($metadata['specs'] ?? null) ? $metadata['specs'] : [];
        $specs = array_merge([
            'cpu' => 2,
            'memory_gb' => 4,
            'disk_gb' => 40,
            'data_disk_gb' => 0,
            'upload_mbps' => 10,
            'download_mbps' => 10,
            'nat_ports' => 20,
        ], $specs);

        $selection = [];
        $monthlyAddon = 0.0;
        $ostype = (string) ($metadata['controller']['ostype'] ?? 'l26');
        $settings = self::settings($product);

        foreach (self::DIMENSIONS as $key => $definition) {
            $requested = $input[$key] ?? null;
            $rows = $options[$key];
            $selected = null;
            $range = $settings[$key]['range'];

            if ($rows === []) {
                $fallbackValue = $key === 'ostype'
                    ? $ostype
                    : (int) ($specs[$key] ?? 0);
                $selected = [
                    'value' => $fallbackValue,
                    'label' => self::label($key, $fallbackValue),
                    'price' => 0.0,
                ];
                $selection[$key] = $selected;
                if ($key === 'ostype') {
                    $ostype = (string) $selected['value'];
                } else {
                    $specs[$key] = (int) $selected['value'];
                }

                continue;
            }

            if ($definition['type'] === 'int' && $range['enabled']) {
                $number = $requested !== null && $requested !== '' ? (float) $requested : (float) $range['min'];
                if ($number < $range['min'] || $number > $range['max']) {
                    throw new InvalidArgumentException("{$definition['label']}超出可选范围。");
                }
                $step = max(0.01, $range['step']);
                $number = $range['min'] + round(($number - $range['min']) / $step) * $step;
                $number = (int) round(max($range['min'], min($range['max'], $number)));
                $selected = [
                    'value' => $number,
                    'label' => self::label($key, $number),
                    'price' => round(max(0, $number - $range['min']) * $range['unit_price'], 2),
                ];
            } else {
                if ($requested !== null && $requested !== '') {
                    foreach ($rows as $row) {
                        if ((string) $row['value'] === (string) $requested) {
                            $selected = $row;
                            break;
                        }
                    }
                } else {
                    $selected = $rows[0];
                }

                if ($selected === null) {
                    throw new InvalidArgumentException("无效的{$definition['label']}选项。");
                }
            }

            $selection[$key] = $selected;
            $monthlyAddon += (float) $selected['price'];

            if ($key === 'ostype') {
                $ostype = (string) $selected['value'];
            } else {
                $specs[$key] = (int) $selected['value'];
            }
        }

        return [
            'specs' => $specs,
            'ostype' => $ostype,
            'selection' => $selection,
            'monthly_addon' => round($monthlyAddon, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, array<int, array{value: int|string, label: string, price: float}>>  $fallback
     * @return array<string, array<int, array{value: int|string, label: string, price: float}>>
     */
    public static function normalizeInput(array $raw, array $fallback): array
    {
        $result = [];
        foreach (self::DIMENSIONS as $key => $definition) {
            $rows = is_array($raw[$key] ?? null) ? $raw[$key] : [];
            $explicitlyEmptied = filter_var($raw[$key]['__deleted'] ?? false, FILTER_VALIDATE_BOOL);
            $normalized = [];
            foreach ($rows as $row) {
                if (! is_array($row) || trim((string) ($row['value'] ?? '')) === '') {
                    continue;
                }
                $value = $definition['type'] === 'int' ? (int) $row['value'] : trim((string) $row['value']);
                if ($definition['type'] === 'int' && $value < 1 && $key !== 'data_disk_gb') {
                    continue;
                }
                $normalized[(string) $value] = [
                    'value' => $value,
                    'label' => trim((string) ($row['label'] ?? '')) ?: self::label($key, $value),
                    'price' => max(0, round((float) ($row['price'] ?? 0), 2)),
                ];
            }

            $result[$key] = ($explicitlyEmptied && $normalized === [])
                ? []
                : ($normalized !== [] ? array_values($normalized) : ($fallback[$key] ?? []));
        }

        return $result;
    }

    public static function osFamily(string $value, string $label = ''): array
    {
        $text = strtolower($value.' '.$label);

        return match (true) {
            str_contains($text, 'windows'), str_contains($text, 'win2k'), str_contains($text, 'win20') => ['windows', 'Windows'],
            str_contains($text, 'ubuntu') => ['ubuntu', 'Ubuntu'],
            str_contains($text, 'debian') => ['debian', 'Debian'],
            str_contains($text, 'centos') => ['centos', 'CentOS'],
            str_contains($text, 'rocky') => ['rocky', 'Rocky Linux'],
            str_contains($text, 'linux'), str_contains($text, 'l26') => ['linux', 'Linux'],
            default => ['other', '其他系统'],
        };
    }

    public static function controllerOSType(string $osType): string
    {
        return match (strtolower($osType)) {
            'win2k22', 'win2022', 'windows2022' => 'win2k22',
            'win2019', 'windows2019' => 'win2019',
            default => 'l26',
        };
    }

    public static function label(string $key, int|string $value): string
    {
        if ($key === 'ostype') {
            return self::OS_OPTIONS[(string) $value] ?? (string) $value;
        }

        if ($key === 'data_disk_gb' && (int) $value === 0) {
            return '无数据盘';
        }

        return $value.self::DIMENSIONS[$key]['unit'];
    }
}
