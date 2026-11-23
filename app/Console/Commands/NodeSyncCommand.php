<?php

namespace App\Console\Commands;

use App\Models\CloudNode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('nodes:sync')]
#[Description('从主控同步节点在线状态、心跳与证书信息')]
class NodeSyncCommand extends Command
{
    public function handle(): int
    {
        try {
            $response = $this->client()->get('/api/nodes')->throw();
        } catch (Throwable $exception) {
            $this->error('节点同步失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $items = $response->json('items', []);
        $updated = 0;

        foreach ($items as $item) {
            $node = CloudNode::query()->where('agent_node_id', $item['id'] ?? null)->first();

            if (! $node) {
                continue;
            }

            $node->update([
                'status' => ($item['status'] ?? 'offline') === 'online' ? 'online' : 'offline',
                'agent_version' => $item['agent_version'] ?? $node->agent_version,
                'agent_url' => $item['url'] ?? $node->agent_url,
                'last_heartbeat_at' => $this->parseDate($item['last_seen'] ?? null) ?? $node->last_heartbeat_at,
                'cert_fingerprint' => $item['cert_fingerprint'] ?? $node->cert_fingerprint,
                'cert_expires_at' => $this->parseDate($item['cert_expires_at'] ?? null) ?? $node->cert_expires_at,
            ]);
            $updated++;
        }

        $this->info("节点同步完成：更新 {$updated} 个节点");

        return self::SUCCESS;
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) config('deviy.controller_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('deviy.timeout', 30));

        $token = (string) config('deviy.controller_token');
        if ($token !== '') {
            $client = $client->withToken($token);
        }

        $options = [];
        $ca = (string) config('deviy.controller_ca');
        $cert = (string) config('deviy.controller_cert');
        $key = (string) config('deviy.controller_key');
        if ($ca !== '') {
            $options['verify'] = $ca;
        }
        if ($cert !== '' && $key !== '') {
            $options['cert'] = $cert;
            $options['ssl_key'] = $key;
        }
        if ($options !== []) {
            $client = $client->withOptions($options);
        }

        return $client;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '' || str_starts_with($value, '0001-')) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
