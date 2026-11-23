<?php

namespace App\Console\Commands;

use App\Services\Commerce\InventoryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:release-stock')]
#[Description('释放超时未支付订单占用的库存')]
class ReleaseStockCommand extends Command
{
    public function handle(InventoryService $inventory): int
    {
        $released = $inventory->releaseExpired();
        $this->info("库存释放完成：{$released} 条预留");

        return self::SUCCESS;
    }
}
