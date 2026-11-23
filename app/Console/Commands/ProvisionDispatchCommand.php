<?php

namespace App\Console\Commands;

use App\Services\Provisioning\ProvisionTaskDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('provision:dispatch {--limit=20}')]
#[Description('把待处理的开通/停复机任务下发到主控与 Agent')]
class ProvisionDispatchCommand extends Command
{
    public function handle(ProvisionTaskDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatch((int) $this->option('limit'));

        $this->info(sprintf(
            '任务下发完成：成功 %d，失败 %d，跳过 %d',
            $result['dispatched'],
            $result['failed'],
            $result['skipped'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
