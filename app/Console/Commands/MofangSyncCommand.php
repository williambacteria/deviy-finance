<?php

namespace App\Console\Commands;

use App\Services\Mofang\MofangCatalogSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('mofang:sync')]
#[Description('同步魔方财务上游商品到本地选品库')]
class MofangSyncCommand extends Command
{
    public function handle(MofangCatalogSync $sync): int
    {
        try {
            $count = $sync->sync();
        } catch (Throwable $exception) {
            $this->error('同步失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('已同步 '.$count.' 条魔方上游商品。');

        return self::SUCCESS;
    }
}
