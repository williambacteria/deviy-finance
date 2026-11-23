<?php

namespace App\Console\Commands;

use App\Models\ReconciliationRun;
use App\Models\Setting;
use App\Services\Finance\ReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('finance:reconcile {--date=}')]
#[Description('每日订单/支付/账单/流水对账并保存快照')]
class FinanceReconcileCommand extends Command
{
    public function handle(ReconciliationService $reconciliation): int
    {
        try {
            $date = $this->option('date')
                ? Carbon::parse((string) $this->option('date'))
                : now()->subDay();
        } catch (Throwable) {
            $this->error('日期格式不正确。');

            return self::FAILURE;
        }

        $result = $reconciliation->check($date->copy()->startOfDay(), $date->copy()->endOfDay());
        $issues = $result['issues'];

        $run = ReconciliationRun::updateOrCreate(
            ['run_date' => $date->toDateString()],
            [
                'checked' => $result['checked'],
                'issues_count' => count($issues),
                'status' => count($issues) === 0 ? 'passed' : 'failed',
                'issues' => $issues,
            ],
        );

        if ($issues !== []) {
            Log::warning('Daily finance reconciliation found differences', [
                'run_date' => $run->run_date,
                'checked' => $run->checked,
                'issues' => count($issues),
            ]);

            $this->notifySupport($run);
            $this->warn('对账发现 '.count($issues).' 项差异，已写入快照并告警。');
        }

        $this->info("对账完成：{$date->toDateString()}，检查 {$run->checked} 单，差异 ".count($issues));

        return self::SUCCESS;
    }

    private function notifySupport(ReconciliationRun $run): void
    {
        $email = (string) Setting::query()->where('key', 'support_email')->value('value');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::raw(
                "Deviy Cloud 财务对账告警\n\n日期：{$run->run_date}\n检查订单：{$run->checked}\n差异：{$run->issues_count}\n\n请登录后台 /admin/reports 查看。",
                function ($message) use ($email): void {
                    $message->to($email)->subject('【Deviy Cloud】财务对账发现差异');
                },
            );
            $run->update(['notified' => true]);
        } catch (Throwable $exception) {
            Log::error('Finance reconciliation alert failed', ['error' => $exception->getMessage()]);
        }
    }
}
