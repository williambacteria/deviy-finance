<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\Billing\RenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('services:bill {--limit=100}')]
#[Description('为开启自动续费且即将到期的服务生成续费订单')]
class ServiceBillCommand extends Command
{
    public function handle(RenewalService $renewals): int
    {
        $services = Service::query()
            ->with('product')
            ->where('auto_renew', true)
            ->whereIn('status', ['active', 'suspended', 'stopped'])
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', now()->addDays(7))
            ->oldest('next_due_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($services as $service) {
            if (\App\Support\Colocation::service($service)) { $skipped++; continue; }
            try {
                $renewals->createOrder($service, $service->billing_cycle ?: 'monthly');
                $created++;
            } catch (Throwable $exception) {
                $failed++;
                $this->warn("服务 #{$service->id} 生成续费订单失败：{$exception->getMessage()}");
            }
        }

        $this->info("续费账单完成：生成 {$created}，跳过 {$skipped}，失败 {$failed}");

        return self::SUCCESS;
    }
}
