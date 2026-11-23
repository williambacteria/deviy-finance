<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:retry {--limit=50}')]
#[Description('重试待发送/失败的邮件与短信通知')]
class NotificationRetryCommand extends Command
{
    public function handle(NotificationDispatcher $dispatcher): int
    {
        $logs = NotificationLog::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 3)
            ->where(fn ($query) => $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->oldest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $sent = 0;
        $failed = 0;
        $pending = 0;

        foreach ($logs as $log) {
            $dispatcher->retry($log);
            $log->refresh();

            match ($log->status) {
                'sent' => $sent++,
                'failed' => $failed++,
                default => $pending++,
            };
        }

        $this->info("通知重试完成：成功 {$sent}，失败 {$failed}，仍待重试 {$pending}");

        return self::SUCCESS;
    }
}
