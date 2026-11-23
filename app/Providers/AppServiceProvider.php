<?php

namespace App\Providers;

use App\Filesystem\S3Client;
use App\Filesystem\S3CompatibleAdapter;
use App\Models\Setting;
use App\Models\UpstreamSupplier;
use App\Services\Mofang\Contracts\MofangGateway;
use App\Services\Mofang\HttpMofangGateway;
use App\Services\Mofang\MockMofangGateway;
use App\Services\Notifications\Contracts\SmsGateway;
use App\Services\Notifications\HttpSmsGateway;
use App\Services\Notifications\LogSmsGateway;
use App\Services\Security\ClamAvScanner;
use App\Services\Security\Contracts\VirusScanner;
use App\Services\Security\NullVirusScanner;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MofangGateway::class, function (): MofangGateway {
            try {
                $supplier = UpstreamSupplier::query()
                    ->where('status', 'active')
                    ->where('driver', 'mofang')
                    ->latest('id')
                    ->first();
            } catch (\Throwable) {
                $supplier = null;
            }

            if ($supplier) {
                return new HttpMofangGateway(
                    $supplier->base_url,
                    $supplier->username,
                    $supplier->api_key,
                    (int) config('mofang.timeout'),
                    (array) config('mofang.endpoints'),
                    (bool) $supplier->verify_ssl,
                    $supplier->password,
                );
            }

            if (config('mofang.driver') === 'http') {
                return new HttpMofangGateway(
                    (string) config('mofang.base_url'),
                    (string) config('mofang.username'),
                    (string) config('mofang.api_key'),
                    (int) config('mofang.timeout'),
                    (array) config('mofang.endpoints'),
                );
            }

            return new MockMofangGateway;
        });

        $this->app->bind(SmsGateway::class, function (): SmsGateway {
            if (config('services.sms.driver') === 'http') {
                return new HttpSmsGateway(
                    (string) config('services.sms.url'),
                    (string) config('services.sms.token'),
                    (string) config('services.sms.sign'),
                );
            }

            return new LogSmsGateway;
        });

        $this->app->bind(VirusScanner::class, function (): VirusScanner {
            if (config('deviy.virus_scanner') === 'clamav') {
                return new ClamAvScanner((string) config('deviy.clamav_binary', 'clamdscan'));
            }

            return new NullVirusScanner;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(\App\Services\RuntimeSettings::class)->apply();
        $this->applyPaymentSettings();
        \App\Services\Payments\WechatPaySettings::apply();

        Storage::extend('s3', function ($app, array $config): FilesystemAdapter {
            $client = new S3Client([
                'endpoint' => (string) ($config['endpoint'] ?? 'https://s3.amazonaws.com'),
                'bucket' => (string) ($config['bucket'] ?? ''),
                'key' => (string) ($config['key'] ?? ''),
                'secret' => (string) ($config['secret'] ?? ''),
                'region' => (string) ($config['region'] ?? 'us-east-1'),
            ]);

            $adapter = new S3CompatibleAdapter($client);

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });

        View::composer(['layouts.site', 'layouts.auth', 'site.*', 'auth.*'], function ($view): void {
            $view->with('siteSettings', once(fn () => Setting::query()->pluck('value', 'key')));
        });
    }

    private function applyPaymentSettings(): void
    {
        try {
            $settings = Setting::query()
                ->where('group_name', 'payment')
                ->whereIn('key', [
                    'payment.alipay_enabled',
                    'payment.alipay_app_id',
                    'payment.alipay_private_key',
                    'payment.alipay_public_key',
                    'payment.alipay_notify_url',
                    'payment.alipay_return_url',
                    'payment.alipay_gateway',
                ])
                ->pluck('value', 'key');
        } catch (\Throwable) {
            return;
        }

        if ($settings->isEmpty()) {
            return;
        }

        if ($settings->has('payment.alipay_enabled')) {
            config(['payments.alipay.enabled' => (string) $settings->get('payment.alipay_enabled') === '1']);
        }

        foreach ([
            'payment.alipay_app_id' => 'app_id',
            'payment.alipay_public_key' => 'public_key',
            'payment.alipay_notify_url' => 'notify_url',
            'payment.alipay_return_url' => 'return_url',
            'payment.alipay_gateway' => 'gateway',
        ] as $settingKey => $configKey) {
            $value = (string) ($settings->get($settingKey) ?? '');
            if ($value !== '') {
                config(['payments.alipay.'.$configKey => $value]);
            }
        }

        $encryptedPrivateKey = (string) ($settings->get('payment.alipay_private_key') ?? '');
        if ($encryptedPrivateKey !== '') {
            try {
                $encryptedPrivateKey = Crypt::decryptString($encryptedPrivateKey);
            } catch (\Throwable) {
                // Support legacy plaintext values written before encryption.
            }
            config(['payments.alipay.private_key' => $encryptedPrivateKey]);
        }
    }
}
