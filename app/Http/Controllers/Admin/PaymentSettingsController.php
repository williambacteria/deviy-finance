<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    private const GROUP = 'payment';

    public function index(): View
    {
        return view('admin.payments.index', [
            'wechat' => $this->wechatViewData(),
            'enabled' => $this->booleanSetting('payment.alipay_enabled', (bool) config('payments.alipay.enabled')),
            'appId' => $this->value('payment.alipay_app_id', (string) config('payments.alipay.app_id')),
            'privateConfigured' => $this->hasPrivateKey(),
            'publicKey' => $this->value('payment.alipay_public_key', (string) config('payments.alipay.public_key')),
            'notifyUrl' => $this->value('payment.alipay_notify_url', (string) config('payments.alipay.notify_url')),
            'returnUrl' => $this->value('payment.alipay_return_url', (string) config('payments.alipay.return_url')),
            'gateway' => $this->value('payment.alipay_gateway', (string) config('payments.alipay.gateway')),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'app_id' => ['nullable', 'string', 'max:80'],
            'private_key' => ['nullable', 'string', 'max:5000'],
            'public_key' => ['nullable', 'string', 'max:5000'],
            'notify_url' => ['nullable', 'url', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:255'],
            'gateway' => ['nullable', 'url', 'max:255'],
        ]);

        $enabled = $request->boolean('enabled');
        $appId = trim((string) ($data['app_id'] ?? ''));
        $privateKey = trim((string) ($data['private_key'] ?? ''));
        $publicKey = trim((string) ($data['public_key'] ?? ''));

        $existingPrivate = $this->hasPrivateKey();
        $existingPublic = $this->value('payment.alipay_public_key', (string) config('payments.alipay.public_key')) !== '';

        if ($enabled && ($appId === '' || (! $existingPrivate && $privateKey === '') || (! $existingPublic && $publicKey === ''))) {
            throw ValidationException::withMessages([
                'enabled' => '开启支付宝前，请先填写 AppID、商户私钥和支付宝公钥。',
            ]);
        }

        $values = [
            'payment.alipay_enabled' => $enabled ? '1' : '0',
            'payment.alipay_app_id' => $appId,
            'payment.alipay_notify_url' => trim((string) ($data['notify_url'] ?? '')),
            'payment.alipay_return_url' => trim((string) ($data['return_url'] ?? '')),
            'payment.alipay_gateway' => trim((string) ($data['gateway'] ?? '')),
        ];
        if ($publicKey !== '' || ! $existingPublic) {
            $values['payment.alipay_public_key'] = $publicKey;
        }

        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['group_name' => self::GROUP, 'key' => $key],
                ['value' => $value, 'type' => 'string'],
            );
        }

        if ($privateKey !== '') {
            Setting::query()->updateOrCreate(
                ['group_name' => self::GROUP, 'key' => 'payment.alipay_private_key'],
                ['value' => Crypt::encryptString($privateKey), 'type' => 'encrypted'],
            );
        } elseif (! $existingPrivate) {
            Setting::query()->updateOrCreate(
                ['group_name' => self::GROUP, 'key' => 'payment.alipay_private_key'],
                ['value' => '', 'type' => 'encrypted'],
            );
        }

        $audit->record($request->user('admin'), 'payments.alipay.updated', '更新支付宝支付配置', null, [
            'enabled' => $enabled,
            'app_id' => $appId,
            'private_key_changed' => $privateKey !== '',
            'public_key_changed' => $publicKey !== '',
        ]);

        return back()->with('status', '支付宝支付配置已保存。');
    }


    private function wechatViewData(): array
    {
        $settings = \App\Services\Payments\WechatPaySettings::configuration();
        $settings['private_configured'] = (string) ($settings['private_key'] ?? '') !== '';
        $settings['api_v3_configured'] = (string) ($settings['api_v3_key'] ?? '') !== '';
        unset($settings['private_key'], $settings['api_v3_key']);
        $settings['mch_id'] = (string) ($settings['mch_id'] ?? '') ?: '1680910160';
        $settings['notify_url'] = route('payments.wechat.notify');
        $settings['platform_public_key'] = \App\Services\Payments\WechatPaySettings::readKey((string) ($settings['platform_public_key'] ?? ''));

        return $settings;
    }

    private function value(string $key, string $default = ''): string
    {
        $value = Setting::query()
            ->where('group_name', self::GROUP)
            ->where('key', $key)
            ->value('value');

        return $value === null ? $default : (string) $value;
    }

    private function booleanSetting(string $key, bool $default): bool
    {
        $value = Setting::query()
            ->where('group_name', self::GROUP)
            ->where('key', $key)
            ->value('value');

        return $value === null ? $default : $value === '1';
    }

    private function hasPrivateKey(): bool
    {
        $value = (string) $this->value('payment.alipay_private_key', '');

        return $value !== '' || (string) config('payments.alipay.private_key') !== '';
    }
}
