<?php

namespace App\Services\Notifications;

use App\Models\Notice;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\Contracts\SmsGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class NotificationDispatcher
{
    public function __construct(private readonly SmsGateway $sms) {}

    public function dispatch(Notice $notice): void
    {
        \App\Services\Mail\MailSettings::ensureTemplates();
        $settings = $this->settings();
        $templates = NotificationTemplate::query()
            ->where('key', $notice->type)
            ->where('enabled', true)
            ->get();

        foreach ($templates as $template) {
            if (! $this->channelEnabled($template->channel, $settings)) {
                continue;
            }
            if (! $notice->user || ! NotificationPreference::allows($notice->user, $template->channel, $notice->type)) {
                continue;
            }

            $recipient = $template->channel === 'email'
                ? (string) $notice->user?->email
                : (string) $notice->user?->phone;

            if ($recipient === '') {
                continue;
            }

            $log = NotificationLog::firstOrCreate(['notice_id' => $notice->id, 'channel' => $template->channel], [
                'user_id' => $notice->user_id,
                'notice_id' => $notice->id,
                'channel' => $template->channel,
                'recipient' => $recipient,
                'template_key' => $notice->type,
                'status' => 'pending',
                'attempts' => 0,
            ]);

            if ($log->wasRecentlyCreated) {
                $this->attempt($notice, $template, $log, $settings);
            }
        }
    }

    public function retry(NotificationLog $log): void
    {
        $log->loadMissing(['notice.user']);

        if (! $log->notice) {
            $log->update(['status' => 'failed', 'error' => '原始通知不存在，无法重试。']);

            return;
        }

        $template = NotificationTemplate::query()
            ->where('key', $log->template_key)
            ->where('channel', $log->channel)
            ->where('enabled', true)
            ->first();

        if (! $template) {
            $log->update(['status' => 'failed', 'error' => '通知模板不存在或已停用。']);

            return;
        }

        $settings = $this->settings();
        if (! $this->channelEnabled($log->channel, $settings)) {
            $log->update(['status' => 'skipped', 'error' => '通知渠道已关闭。']);

            return;
        }

        if ($log->notice->user && ! NotificationPreference::allows($log->notice->user, $log->channel, $log->template_key)) {
            $log->update(['status' => 'skipped', 'error' => '用户已退订该通知。']);

            return;
        }

        $this->attempt($log->notice, $template, $log, $settings);
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function attempt(Notice $notice, NotificationTemplate $template, NotificationLog $log, array $settings): void
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('notification-delivery:'.$log->id, 120);
        if (! $lock->get()) {
            return;
        }
        try {
            $log->refresh();
            if ($log->status === 'sent') {
                return;
            }
            $this->deliver($notice, $template, $log, $settings);
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, string> $settings */
    private function deliver(Notice $notice, NotificationTemplate $template, NotificationLog $log, array $settings): void
    {
        if (in_array($notice->type, ['service_due', 'service_expired'], true)) {
            $service = $notice->user?->services()->find(data_get($notice->data, 'service_id'));
            $expected = data_get($notice->data, 'expires_at');
            if (! $expected || ! $service || ! in_array($service->status, ['active', 'suspended', 'stopped'], true) || data_get($service->specs, 'admin_deletion') || ($expected && $service->expires_at?->toIso8601String() !== $expected)) {
                $log->update(['status' => 'skipped', 'error' => '资源已续费、终止或记录已变更，取消旧的到期邮件。', 'next_retry_at' => null]);
                return;
            }
        }
        $quietUntil = $this->quietUntil($notice->user);
        if ($quietUntil) {
            $log->update([
                'status' => 'pending',
                'error' => '用户免打扰时段，延后到 '.$quietUntil->format('Y-m-d H:i').' 发送。',
                'next_retry_at' => $quietUntil,
            ]);

            return;
        }

        $limit = (int) ($settings['rate_limit_per_hour'] ?? 20);
        if ($limit > 0) {
            $recent = NotificationLog::query()
                ->where('user_id', $notice->user_id)
                ->where('channel', $log->channel)
                ->where('id', '!=', $log->id)
                ->where('status', 'sent')->where('sent_at', '>=', now()->subHour())
                ->count();

            if ($recent >= $limit) {
                $log->update([
                    'status' => 'pending',
                    'next_retry_at' => now()->addHour(),
                    'error' => '达到每小时发送上限，延后重试（'.$limit.' 条）。',
                ]);

                return;
            }
        }

        $rendered = $this->render($template, $notice, (string) ($settings['sms_sign'] ?? config('services.sms.sign', 'Deviy')));
        $attempts = $log->attempts + 1;

        try {
            if ($template->channel === 'email') {
                if (! \App\Services\Mail\MailSettings::plugin()->is_enabled) {
                    throw new \RuntimeException('邮件通道已停用。');
                }
                \App\Services\Mail\MailSettings::apply();
                Mail::html($rendered['body'], function ($message) use ($log, $rendered): void {
                    $config = \App\Services\Mail\MailSettings::configuration();
                    $message->from((string) $config['from_address'], (string) $config['from_name'])->sender((string) $config['username'])->to($log->recipient)->subject($rendered['subject']);
                });
            } elseif ($template->channel === 'sms') {
                $this->sms->send($log->recipient, $rendered['body']);
            }

            $log->update([
                'status' => 'sent',
                'attempts' => $attempts,
                'error' => null,
                'next_retry_at' => null,
                'payload' => ['subject' => $rendered['subject'], 'body' => $rendered['body']],
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => $attempts >= 3 ? 'failed' : 'pending',
                'attempts' => $attempts,
                'error' => mb_substr($exception->getMessage(), 0, 1000),
                'next_retry_at' => $attempts >= 3 ? null : now()->addMinutes($attempts * 5),
                'payload' => ['subject' => $rendered['subject']],
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        return Setting::query()
            ->where('group_name', 'notification')
            ->pluck('value', 'key')
            ->map(fn ($value): string => (string) $value)
            ->all();
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function channelEnabled(string $channel, array $settings): bool
    {
        return $this->isEnabled($settings[$channel.'_enabled'] ?? 'false');
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function render(NotificationTemplate $template, Notice $notice, string $sign): array
    {
        $serviceId = $notice->data['service_id'] ?? null;
        $service = $serviceId ? $notice->user?->services()->find($serviceId) : null;
        $orderId = $notice->data['order_id'] ?? null;
        $order = $orderId ? $notice->user?->orders()->find($orderId) : null;

        $replacements = [
            'customer' => (string) $notice->user?->name,
            'name' => (string) ($service?->name ?? $order?->order_no ?? $notice->title),
            'service_no' => (string) ($service?->service_no ?? ''),
            'date' => (string) ($service?->expires_at?->format('Y-m-d H:i') ?? ''),
            'days' => (string) ($notice->data['days'] ?? ''),
            'percent' => (string) ($notice->data['percent'] ?? ''),
            'used' => number_format(((int) ($notice->data['used'] ?? 0)) / 1024 / 1024, 1).' MB',
            'limit' => number_format(((int) ($notice->data['limit'] ?? 0)) / 1024 / 1024, 1).' MB',
            'sign' => $sign,
            'unsubscribe_url' => URL::signedRoute('notifications.unsubscribe', [
                'user' => $notice->user_id,
                'type' => $notice->type,
                'channel' => $template->channel,
            ]),
        ];

        $replace = function (string $value) use ($replacements): string {
            foreach ($replacements as $key => $replacement) {
                $value = str_replace('{{'.$key.'}}', $replacement, $value);
            }

            return $value;
        };

        if ($template->channel === 'email') {
            $ticketId = data_get($notice->data, 'ticket_id');
            $ticket = $ticketId ? $notice->user?->tickets()->find($ticketId) : null;
            $paymentId = data_get($notice->data, 'payment_id');
            $payment = $paymentId ? $notice->user?->payments()->find($paymentId) : null;
            $replacements = array_merge($replacements, [
                'service_name' => (string) ($service?->name ?? ''),
                'date' => $service?->expires_at?->format('Y-m-d H:i').'（北京时间）',
                'due_description' => (int) data_get($notice->data, 'days') > 0 ? '距离到期还有 '.data_get($notice->data, 'days').' 天' : '请留意今天的到期时间',
                'console_url' => $service ? route('console.services.show', $service) : route('console.services.index'),
                'order_no' => (string) $order?->order_no,
                'order_url' => $order ? route('console.orders.show', $order) : route('console.orders.index'),
                'amount' => $order || $payment ? \App\Support\Money::format($order?->total ?? $payment?->amount) : '',
                'payment_no' => (string) $payment?->payment_no,
                'ticket_no' => (string) $ticket?->ticket_no,
                'ticket_subject' => (string) $ticket?->subject,
                'ticket_url' => $ticket ? route('console.tickets.show', $ticket) : route('console.tickets.index'),
                'sign' => (string) \App\Services\Mail\MailSettings::configuration()['from_name'],
            ]);

            return \App\Services\Mail\MailPresentation::render($template, $notice->user, $replacements);
        }
        $body = $replace($template->body);

        return [
            'subject' => $replace((string) ($template->subject ?? $notice->title)),
            'body' => $body,
        ];
    }

    private function isEnabled(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    private function quietUntil(?User $user): ?Carbon
    {
        if (! $user || ! $user->quiet_hours_start || ! $user->quiet_hours_end) {
            return null;
        }

        $now = now();
        $start = $now->copy()->setTimeFromTimeString((string) $user->quiet_hours_start);
        $end = $now->copy()->setTimeFromTimeString((string) $user->quiet_hours_end);

        if ($start->equalTo($end)) {
            return null;
        }

        if ($start->lessThan($end)) {
            return $now->greaterThanOrEqualTo($start) && $now->lessThan($end) ? $end : null;
        }

        if ($now->greaterThanOrEqualTo($start)) {
            return $end->addDay();
        }

        return $now->lessThan($end) ? $end : null;
    }
}
