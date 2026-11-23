<?php

namespace App\Services;

use App\Models\OpsAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpsWebhookNotifier
{
    public function send(OpsAlert $alert): void
    {
        $url = (string) config('deviy.ops_webhook_url', '');
        if ($url === '') {
            return;
        }

        try {
            $request = Http::timeout(5)->acceptJson();
            $token = (string) config('deviy.ops_webhook_token', '');
            if ($token !== '') {
                $request = $request->withToken($token);
            }
            $request->post($url, [
                'text' => '[Deviy Cloud] ['.$alert->severity.'] '.$alert->title,
                'source' => $alert->source,
                'key' => $alert->key,
                'body' => $alert->body,
                'status' => $alert->status,
                'occurrences' => $alert->occurrences,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Ops webhook failed', ['error' => $exception->getMessage(), 'key' => $alert->key]);
        }
    }
}
