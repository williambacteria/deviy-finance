<?php

namespace App\Services\Mail;

use App\Models\NotificationTemplate;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailSettings
{
    public const CODE = 'mail';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'host' => 'smtpdm.aliyun.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'panel@deviy.cn',
            'password' => '',
            'from_address' => 'no-reply@deviy.cn',
            'from_name' => '德威云计算Deviy Cloud',
        ];
    }

    public static function plugin(): Plugin
    {
        return Plugin::query()->firstOrCreate(
            ['code' => self::CODE],
            [
                'name' => '邮件通知',
                'type' => 'notification',
                'version' => '1.0.0',
                'description' => '阿里云 DirectMail SMTP 邮件通知，支持注册验证、账号欢迎和服务器开通提醒。',
                'is_enabled' => true,
                'config' => self::defaults(),
            ],
        );
    }

    /** @return array<string, mixed> */
    public static function configuration(): array
    {
        $config = self::plugin()->config;

        return array_merge(self::defaults(), is_array($config) ? $config : []);
    }

    public static function apply(): void
    {
        $config = self::configuration();
        $encryption = (string) ($config['encryption'] ?? 'ssl');
        $scheme = in_array($encryption, ['ssl', 'smtps'], true) ? 'smtps' : 'smtp';

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.host' => (string) ($config['host'] ?? ''),
            'mail.mailers.smtp.port' => (int) ($config['port'] ?? 465),
            'mail.mailers.smtp.username' => (string) ($config['username'] ?? ''),
            'mail.mailers.smtp.password' => (string) ($config['password'] ?? ''),
            'mail.from.address' => (string) ($config['from_address'] ?? ''),
            'mail.from.name' => (string) ($config['from_name'] ?? ''),
        ]);
    }

    public static function sendTemplate(string $key, User $user, array $data = [], ?string $to = null): bool
    {
        self::ensureTemplates();

        $template = NotificationTemplate::query()
            ->where('key', $key)
            ->where('channel', 'email')
            ->where('enabled', true)
            ->first();

        if (! $template || ! self::plugin()->is_enabled) {
            return false;
        }

        $rendered = MailPresentation::render($template, $user, array_merge(['sign' => (string) self::configuration()['from_name']], $data));

        try {
            self::apply();
            $mailConfig = self::configuration();
            Mail::html($rendered['body'], function ($message) use ($rendered, $to, $user, $mailConfig): void {
                $message->from((string) $mailConfig['from_address'], (string) $mailConfig['from_name'])
                    ->sender((string) $mailConfig['username'])->to($to ?: (string) $user->email)->subject($rendered['subject']);
            });

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public static function ensureTemplates(): void
    {
        foreach (MailPresentation::definitions() as $key => $data) {
            NotificationTemplate::query()->firstOrCreate(
                ['key' => $key, 'channel' => 'email'],
                ['subject' => $data['subject'], 'body' => $data['body'], 'enabled' => true],
            );
        }
    }
}
