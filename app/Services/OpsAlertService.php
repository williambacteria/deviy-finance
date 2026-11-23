<?php

namespace App\Services;

use App\Models\OpsAlert;

class OpsAlertService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function raise(string $key, string $source, string $severity, string $title, string $body, array $data = []): OpsAlert
    {
        $alert = OpsAlert::query()->firstOrNew(['key' => $key]);
        $wasResolved = $alert->exists && $alert->status === 'resolved';

        $alert->fill([
            'source' => $source,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'last_seen_at' => now(),
        ]);

        if (! $alert->exists) {
            $alert->status = 'open';
            $alert->occurrences = 1;
        } else {
            $alert->occurrences = (int) $alert->occurrences + 1;
            if ($wasResolved) {
                $alert->status = 'open';
                $alert->resolved_at = null;
            }
        }

        $alert->save();

        return $alert;
    }

    /**
     * @param  array<int, string>  $activeKeys
     */
    public function resolveSourceExcept(string $source, array $activeKeys): int
    {
        return OpsAlert::query()
            ->where('source', $source)
            ->where('status', '!=', 'resolved')
            ->when($activeKeys !== [], fn ($query) => $query->whereNotIn('key', $activeKeys))
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
