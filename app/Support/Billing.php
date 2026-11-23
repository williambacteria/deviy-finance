<?php

namespace App\Support;

final class Billing
{
    /**
     * @return array<string, array{label: string, months: int}>
     */
    public static function cycles(): array
    {
        return [
            'monthly' => ['label' => '月付', 'months' => 1],
            'quarterly' => ['label' => '季付', 'months' => 3],
            'semiannually' => ['label' => '半年付', 'months' => 6],
            'annually' => ['label' => '年付', 'months' => 12],
        ];
    }

    public static function label(string $cycle): string
    {
        return $cycle === 'onetime' ? '一次性' : (self::cycles()[$cycle]['label'] ?? $cycle);
    }

    public static function months(string $cycle): int
    {
        return self::cycles()[$cycle]['months'] ?? 1;
    }
}
