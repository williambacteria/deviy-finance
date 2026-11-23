<?php

namespace App\Support;

use Illuminate\Support\Number;

final class Money
{
    public static function add(string|float|int $left, string|float|int $right): string
    {
        return bcadd((string) $left, (string) $right, 6);
    }

    public static function subtract(string|float|int $left, string|float|int $right): string
    {
        return bcsub((string) $left, (string) $right, 6);
    }

    public static function compare(string|float|int $left, string|float|int $right): int
    {
        return bccomp((string) $left, (string) $right, 6);
    }

    public static function format(string|float|int $amount): string
    {
        return '¥'.number_format((float) $amount, 2);
    }

    public static function compact(string|float|int $amount): string
    {
        return Number::format((float) $amount, maxPrecision: 2);
    }
}
