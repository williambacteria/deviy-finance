<?php

namespace App\Services\Security;

use App\Models\Notice;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Validation\ValidationException;

class AttachmentQuota
{
    public function limitFor(User $user): int
    {
        $group = (string) ($user->customer_group ?: 'standard');
        $group = $group === 'default' ? 'standard' : $group;
        $setting = Setting::query()
            ->where('group_name', 'attachment')
            ->where('key', 'quota_'.$group.'_mb')
            ->value('value');
        if (is_numeric($setting) && (int) $setting > 0) {
            return (int) $setting * 1024 * 1024;
        }

        $groups = (array) config('deviy.attachment_quota_groups', []);

        return (int) ($groups[$group] ?? config('deviy.ticket_attachment_quota', 50 * 1024 * 1024));
    }

    public function usedBy(User $user): int
    {
        return (int) TicketAttachment::query()
            ->whereHas('ticket', fn ($ticket) => $ticket->where('user_id', $user->id))
            ->sum('size');
    }

    public function assertCanUpload(User $user, int $incomingBytes, ?Ticket $ticket = null): void
    {
        $limit = $this->limitFor($user);
        $used = $this->usedBy($user);

        if ($ticket !== null) {
            $used -= (int) $ticket->attachments()->sum('size');
        }

        if ($used + $incomingBytes > $limit) {
            throw ValidationException::withMessages([
                'attachments' => '客户附件容量不足，当前上限 '.round($limit / 1024 / 1024).' MB。',
            ]);
        }
    }

    public function warnIfNearLimit(User $user): void
    {
        $limit = $this->limitFor($user);
        if ($limit <= 0) {
            return;
        }

        $used = $this->usedBy($user);
        $percent = (int) floor(($used / $limit) * 100);
        if ($percent < 80) {
            return;
        }

        $threshold = min(100, (int) (floor($percent / 10) * 10));
        $notice = Notice::firstOrCreate(
            ['dedupe_key' => 'attachment_quota:'.$user->id.':'.now()->format('Y-m').':'.$threshold],
            [
                'user_id' => $user->id,
                'type' => 'attachment_quota',
                'title' => '附件容量接近上限',
                'body' => '你的工单附件已使用 '.$percent.'%，上限 '.round($limit / 1024 / 1024).' MB。请清理历史附件或联系客服提升配额。',
                'data' => ['used' => $used, 'limit' => $limit, 'percent' => $percent],
            ],
        );

        if ($notice->wasRecentlyCreated) {
            app(NotificationDispatcher::class)->dispatch($notice);
        }
    }
}
