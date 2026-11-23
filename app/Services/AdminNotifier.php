<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminNotifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyRole(string $role, string $type, string $title, string $body, array $data = [], ?string $dedupeKey = null, ?int $excludeAdminId = null): int
    {
        $admins = Admin::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->when($excludeAdminId, fn ($query) => $query->whereKeyNot($excludeAdminId))
            ->get();

        return $this->notify($admins, $type, $title, $body, $data, $dedupeKey);
    }

    /**
     * @param  Collection<int, Admin>  $admins
     * @param  array<string, mixed>  $data
     */
    public function notify(Collection $admins, string $type, string $title, string $body, array $data = [], ?string $dedupeKey = null): int
    {
        $created = 0;
        foreach ($admins as $admin) {
            $key = $dedupeKey ?? Str::uuid()->toString();
            $notice = AdminNotice::firstOrCreate(
                ['admin_id' => $admin->id, 'dedupe_key' => $key],
                [
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                ],
            );
            if ($notice->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
