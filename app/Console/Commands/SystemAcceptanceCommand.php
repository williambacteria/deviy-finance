<?php

namespace App\Console\Commands;

use App\Services\SystemAcceptanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:acceptance {--json}')]
#[Description('生产验收：数据库、缓存、支付、队列、网络、存储、主控与 Agent')]
class SystemAcceptanceCommand extends Command
{
    public function handle(SystemAcceptanceService $acceptance): int
    {
        $checks = $acceptance->run();

        if ($this->option('json')) {
            $this->line((string) json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['检查项', '状态', '说明'],
                array_map(fn (array $check): array => [$check['check'], $check['status'], $check['message']], $checks),
            );
        }

        $failed = collect($checks)->where('status', 'fail')->count();
        $warnings = collect($checks)->where('status', 'warn')->count();
        $passed = count($checks) - $failed - $warnings;

        $this->info("验收完成：失败 {$failed}，警告 {$warnings}，通过 {$passed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
