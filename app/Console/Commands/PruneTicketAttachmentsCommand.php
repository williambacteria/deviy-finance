<?php

namespace App\Console\Commands;

use App\Models\TicketAttachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('tickets:prune-attachments {--days=90} {--dry-run}')]
#[Description('清理已关闭工单的过期附件与文件')]
class PruneTicketAttachmentsCommand extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = TicketAttachment::query()
            ->where('created_at', '<', $cutoff)
            ->whereHas('ticket', fn ($ticket) => $ticket->where('status', 'closed'));

        $count = 0;
        $bytes = 0;

        $query->chunkById(100, function ($attachments) use ($dryRun, &$count, &$bytes): void {
            foreach ($attachments as $attachment) {
                $count++;
                $bytes += $attachment->size;

                if ($dryRun) {
                    continue;
                }

                Storage::disk($attachment->disk ?: 'local')->delete($attachment->path);
                $attachment->delete();
            }
        });

        $this->info(sprintf(
            '%s：%d 个附件，%s MB（关闭工单超过 %d 天）',
            $dryRun ? '将清理' : '已清理',
            $count,
            number_format($bytes / 1024 / 1024, 2),
            $days,
        ));

        return self::SUCCESS;
    }
}
