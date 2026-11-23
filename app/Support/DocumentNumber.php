<?php

namespace App\Support;

use Illuminate\Support\Str;

final class DocumentNumber
{
    public static function make(string $prefix): string
    {
        $key = ['ORD' => 'order_prefix', 'INV' => 'invoice_prefix'][$prefix] ?? null;
        $configured = $key ? (string) \App\Models\Setting::value($key, $prefix) : $prefix;
        if (preg_match('/^[A-Z][A-Z0-9]{1,7}$/D', $configured)) {
            $prefix = $configured;
        }

        return $prefix.'-'.now()->format('Ym').'-'.Str::upper(Str::random(6));
    }
}
