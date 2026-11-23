<?php

namespace App\Console\Commands;

use App\Models\CloudNode;
use App\Models\NetworkDriftCheck;
use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('network:check {--limit=100}')]
#[Description('巡检各节点 NAT 实际规则与期望规则的漂移')]
class NetworkDriftCheckCommand extends Command
{
    public function handle(): int
    {
        $nodes = CloudNode::query()
            ->whereNotNull('agent_node_id')
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $clean = 0;
        $drift = 0;
        $errors = 0;

        foreach ($nodes as $node) {
            try {
                $response = $this->client()
                    ->get('/api/nodes/'.$node->agent_node_id.'/network/verify', ['kind' => 'nat'])
                    ->throw()
                    ->json();

                $verified = (bool) ($response['verified'] ?? false);
                $missing = $response['missing'] ?? [];
                $extra = $response['extra'] ?? [];
                $existing = NetworkDriftCheck::query()->where('node_id', $node->id)->first();
                $action = 'none';
                $repairStatus = null;
                $repairResult = null;
                $repairedAt = null;
                $status = $verified ? 'clean' : 'drift';

                if (! $verified && $node->network_policy === 'auto_repair') {
                    $impact = count($missing) + count($extra);
                    $threshold = (int) config('deviy.network_repair_threshold', 20);

                    if ($impact > $threshold) {
                        $action = 'approval';
                    } else {
                        $action = 'auto_repair';
                        $repairResult = $this->client()
                            ->post('/api/nodes/'.$node->agent_node_id.'/network/repair', [
                                'kind' => 'nat',
                                'confirm' => true,
                            ])
                            ->throw()
                            ->json();
                        $repairStatus = (string) ($repairResult['status'] ?? 'unknown');
                        $repairedAt = now();
                        if ((bool) ($repairResult['after']['verified'] ?? false)) {
                            $status = 'clean';
                            $missing = [];
                            $extra = [];
                        } else {
                            $status = 'drift';
                        }
                    }
                } elseif (! $verified) {
                    $action = 'alert';
                }

                NetworkDriftCheck::updateOrCreate(
                    ['node_id' => $node->id],
                    [
                        'status' => $status,
                        'action' => $action,
                        'repair_status' => $repairStatus,
                        'repair_result' => $repairResult,
                        'repaired_at' => $repairedAt,
                        'expected_count' => count($response['expected'] ?? []),
                        'actual_count' => count($response['actual'] ?? []),
                        'missing' => $missing,
                        'extra' => $extra,
                        'last_error' => null,
                        'checked_at' => now(),
                    ],
                );

                if ($status === 'clean') {
                    $clean++;
                } else {
                    $drift++;
                    Log::warning('Network drift detected', [
                        'node' => $node->name,
                        'missing' => $missing,
                        'extra' => $extra,
                    ]);
                    if ($existing?->status !== 'drift' || $existing?->notified_at === null || $existing->notified_at->diffInMinutes(now()) > 60) {
                        $this->notifySupport($node, $missing, $extra);
                    }
                }
            } catch (Throwable $exception) {
                $errors++;
                NetworkDriftCheck::updateOrCreate(
                    ['node_id' => $node->id],
                    [
                        'status' => 'error',
                        'missing' => null,
                        'extra' => null,
                        'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                        'checked_at' => now(),
                    ],
                );
            }
        }

        $this->info("网络巡检完成：正常 {$clean}，漂移 {$drift}，异常 {$errors}");

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

    /**
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $extra
     */
    private function notifySupport(CloudNode $node, array $missing, array $extra): void
    {
        $email = (string) Setting::query()->where('key', 'support_email')->value('value');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::raw(
                "Deviy Cloud 网络漂移告警\n\n节点：{$node->name}\n缺失：".implode(', ', $missing)."\n多余：".implode(', ', $extra)."\n\n请登录后台 /admin/network-drift 查看。",
                function ($message) use ($email): void {
                    $message->to($email)->subject('【Deviy Cloud】网络规则漂移告警');
                },
            );
            NetworkDriftCheck::query()->where('node_id', $node->id)->update(['notified_at' => now()]);
        } catch (Throwable $exception) {
            Log::error('Network drift alert failed', ['error' => $exception->getMessage()]);
        }
    }
}
