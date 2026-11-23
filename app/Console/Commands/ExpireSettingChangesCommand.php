<?php

namespace App\Console\Commands;

use App\Models\SettingChangeRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('settings:expire-changes {--days=7}')]
#[Description('自动关闭超时未审批的设置变更')]
class ExpireSettingChangesCommand extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $count = SettingChangeRequest::query()
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->update([
                'status' => 'expired',
                'reason' => '超过 '.$days.' 天未审批，自动关闭',
                'reviewed_at' => now(),
            ]);

        $this->info("已自动关闭 {$count} 项超时设置变更。");

        return self::SUCCESS;
    }
}
