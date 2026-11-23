<?php

namespace App\Console\Commands;

use App\Models\Notice;
use App\Models\Service;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('services:notify')]
#[Description('为即将到期/已过期服务生成站内通知，并发送邮件/短信')]
class ServiceNotifyCommand extends Command
{
    public function handle(NotificationDispatcher $dispatcher): int
    {
        $created = 0;

        $services = Service::query()->with('user')->whereIn('status', ['active', 'suspended', 'stopped'])
            ->whereNotNull('expires_at')->where('expires_at', '>=', now()->subDay()->startOfDay())
            ->where('expires_at', '<=', now()->addDays(7)->endOfDay())->orderBy('id')->lazyById(200);
        foreach ($services as $service) {
            if (! $service->user || data_get($service->specs, 'admin_deletion') || data_get($service->specs, 'legacy_import.operation_hold', false)) {
                continue;
            }
            $days = (int) now()->startOfDay()->diffInDays($service->expires_at->copy()->startOfDay(), false);
            $expired = $service->expires_at->isPast();
            if ((! $expired && ! in_array($days, [7, 3, 1, 0], true)) || ($expired && $service->expires_at->lt(now()->subDay()))) {
                continue;
            }
            $type = $expired ? 'service_expired' : 'service_due';
            $key = $type.':'.$service->id.':'.$service->expires_at->timestamp.':'.($expired ? 'expired' : $days);
            $notice = Notice::firstOrCreate(['dedupe_key' => $key], [
                'user_id' => $service->user_id, 'type' => $type,
                'title' => $expired ? '服务已到期，请核对续费' : ($days === 0 ? '服务将在今天到期' : "服务将在 {$days} 天后到期"),
                'body' => $service->name.'（'.$service->service_no.'）到期时间：'.$service->expires_at->format('Y-m-d H:i').'。请及时核对续费安排。',
                'data' => ['service_id' => $service->id, 'days' => $days, 'expires_at' => $service->expires_at->toIso8601String()],
            ]);
            if ($notice->wasRecentlyCreated) {
                $created++;
                $dispatcher->dispatch($notice);
            }
        }

        $this->info("到期通知完成：生成 {$created}");

        return self::SUCCESS;
    }
}
