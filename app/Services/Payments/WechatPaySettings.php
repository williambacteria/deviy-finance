<?php

namespace App\Services\Payments;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class WechatPaySettings
{
    public const FIELDS = ['enabled', 'app_id', 'mch_id', 'serial_no', 'private_key', 'platform_public_key', 'platform_key_id', 'api_v3_key', 'notify_url'];

    public static function configuration(): array
    {
        $values = (array) config('payments.wechat', []);
        $rows = Setting::query()->where('group_name', 'payment')->whereIn('key', array_map(fn (string $field): string => 'payment.wechat_'.$field, self::FIELDS))->get();
        foreach ($rows as $row) {
            $field = substr($row->key, strlen('payment.wechat_'));
            $value = (string) $row->value;
            if (in_array($field, ['private_key', 'api_v3_key'], true) && $value !== '') {
                $value = Crypt::decryptString($value);
            }
            $values[$field] = $field === 'enabled' ? $value === '1' : $value;
        }
        $values['notify_url'] = $values['notify_url'] ?? '';
        $values['platform_key_id'] = $values['platform_key_id'] ?? '';

        return $values;
    }

    public static function apply(): void
    {
        try {
            config(['payments.wechat' => self::configuration()]);
        } catch (\Throwable $exception) {
            config(['payments.wechat.enabled' => false]);
            report($exception);
        }
    }

    public static function readKey(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_contains($value, 'BEGIN') || ! is_file($value) ? $value : (string) file_get_contents($value);
    }
}
