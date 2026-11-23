<?php

namespace App\Console\Commands;

use App\Models\UpstreamOrder;
use App\Services\Mofang\MofangFulfillmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('mofang:fulfill {--limit=20}')]
#[Description('处理魔方上游待下单、待支付、待开通订单')]
class MofangFulfillCommand extends Command
{
    public function handle(MofangFulfillmentService $fulfillment): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $orders = UpstreamOrder::query()
            ->with('service')
            ->whereIn('status', ['pending', 'ordered', 'paid', 'provisioning', 'awaiting_payment', 'confirming'])
            ->where('attempts', '<', 20)
            ->oldest('id')
            ->limit($limit)
            ->get();

        $succeeded = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $fulfillment->process($order);
                $succeeded++;
            } catch (Throwable $exception) {
                $failed++;
                $this->warn("上游订单 #{$order->id} 处理失败：{$exception->getMessage()}");
            }
        }

        $this->info("魔方履约完成：成功 {$succeeded}，失败 {$failed}，本轮处理 {$orders->count()}");

        return self::SUCCESS;
    }
}
