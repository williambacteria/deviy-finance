<?php

namespace App\Services\Legacy;

use App\Models\LedgerEntry;
use App\Models\LegacyImportRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class LegacyFinanceBridge
{
    public function __construct(private LegacyFinanceMirror $mirror) {}

    public function handle(Request $request): Response
    {
        $path = '/'.ltrim($request->path(), '/');
        abort_unless($this->allows($path), 404);
        if ($request->query->has('s')) {
            abort(400, 'Invalid API routing parameter.');
        }
        $headers = array_filter(['Host' => 'www.deviy.cn', 'Accept' => $request->header('Accept', 'application/json'), 'Authorization' => $request->header('Authorization'), 'Cookie' => $request->header('Cookie'), 'Content-Type' => $request->header('Content-Type', 'application/x-www-form-urlencoded'), 'X-Forwarded-For' => $request->ip(), 'X-Real-IP' => $request->ip()]);
        $query = $request->query();
        $login = $path === '/zjmf_api_login';
        $catalog = str_starts_with($path, '/api/product/') || $path === '/api/product';
        if (! $login && ! $catalog) {
            $authentication = Http::withHeaders($headers)->connectTimeout(3)->timeout(20)->get('http://127.0.0.1:18081/host/list', ['limit' => 1]);
            if (! $authentication->successful() || (int) $authentication->json('status', 0) !== 200) {
                return response($authentication->body(), $authentication->status())->header('Content-Type', 'application/json');
            }
        }
        $lockPath = storage_path('app/private/legacy-finance-api.lock');
        $lock = fopen($lockPath, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return response(['status' => 429, 'msg' => 'API 请求正在处理中，请稍后重试。'], 429)->header('Retry-After', '2');
        }
        try {
            $pdo = $this->connection();
            $this->recover($pdo);
            $mapping = LegacyImportRecord::query()->where('source', 'www.deviy.cn')->where('source_table', 'shd_clients')->where('source_id', '1')->firstOrFail();
            $reply = DB::transaction(function () use ($mapping, $pdo, $request, $path, $query, $headers): Response {
                $user = User::query()->whereKey($mapping->target_id)->lockForUpdate()->firstOrFail();
                if (! $user->isActive()) {
                    return response(['status' => 403, 'msg' => '账户已暂停。'], 403);
                }
                $this->mirror->exportServices($pdo, $user);
                $statement = $pdo->prepare('UPDATE shd_clients SET credit = ? WHERE id = 1');
                $statement->execute([number_format((float) $user->balance, 2, '.', '')]);
                $this->journalConnection();
                $journal = new LegacyImportRecord;
                $journal->setConnection('legacy_api_journal');
                $journal->fill(['source' => 'legacy_api_compat', 'source_table' => 'request', 'source_id' => (string) Str::uuid(), 'target_table' => 'users', 'target_id' => $user->id, 'state' => 'in_flight', 'payload' => ['credit_before' => number_format((float) $user->balance, 2, '.', ''), 'path' => $path, 'method' => $request->method(), 'started_at' => now()->toIso8601String()]]);
                $journal->save();
                $url = 'http://127.0.0.1:18081'.$path.($query !== [] ? '?'.http_build_query($query) : '');
                $client = Http::withHeaders($headers)->connectTimeout(3)->timeout(210)->withOptions(['allow_redirects' => false]);
                try {
                    $remote = $client->withBody($request->getContent(), $headers['Content-Type'])->send($request->method(), $url);
                } catch (\Throwable $exception) {
                    $journal->state = 'uncertain';
                    $journal->save();
                    report($exception);
                    throw new RuntimeException('API 执行结果待核对，请勿重复提交付款或开通请求。');
                }
                $creditAfter = (string) $pdo->query('SELECT credit FROM shd_clients WHERE id = 1')->fetchColumn();
                $this->settle($pdo, $user, $journal->id, $creditAfter);
                $response = response($remote->body(), $remote->status())->header('Content-Type', $remote->header('Content-Type') ?: 'application/json');
                foreach (['Location', 'Cache-Control'] as $name) {
                    if ($remote->header($name)) {
                        $response->header($name, $remote->header($name));
                    }
                }
                foreach ($remote->toPsrResponse()->getHeader('Set-Cookie') as $cookie) {
                    $response->headers->set('Set-Cookie', $cookie, false);
                }

                return $response;
            });

            return $reply;
        } catch (\Throwable $exception) {
            report($exception);

            return response(['status' => 503, 'msg' => 'API 处理暂时未完成，请稍后核对实例或账单后重试。'], 503);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function allows(string $path): bool
    {
        return preg_match('#^/(?:zjmf_api_login|apply_credit|cart/(?:get_product_config|add_to_shop|settle|add_promo)|api/(?:product(?:/[a-zA-Z0-9_-]+(?:/resource)?)?|host/sync)|host/(?:list|header|renew|cancel|dedicatedserver|trafficusage)|provision/(?:default|chart/\d+|custom/(?:\d+|content))|zjmf_api/provision/custom/content|dcim/(?:on|off|reboot|bmc|novnc|rescue|reinstall|crack_pass|traffic_usage|traffic_package|buy_flow_packet|resintall_status|refresh_power_status|detail))/?$#D', $path) === 1;
    }

    public function connection(): PDO
    {
        $cfg = require '/www/wwwroot/www.deviy.cn/app/config/database.php';

        return new PDO('mysql:host='.$cfg['hostname'].';port='.($cfg['hostport'] ?: 3306).';dbname='.$cfg['database'].';charset=utf8mb4', $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function journalConnection(): void
    {
        if (! config('database.connections.legacy_api_journal')) {
            config(['database.connections.legacy_api_journal' => config('database.connections.'.config('database.default'))]);
        }
    }

    public function recover(PDO $pdo): void
    {
        $pending = LegacyImportRecord::query()->where('source', 'legacy_api_compat')->where('source_table', 'request')->whereIn('state', ['in_flight', 'uncertain'])->orderBy('id')->get();
        foreach ($pending as $journal) {
            if ($journal->state === 'uncertain' || $journal->created_at->greaterThan(now()->subMinutes(5))) {
                throw new RuntimeException('Previous API outcome requires reconciliation.');
            }
            DB::transaction(function () use ($pdo, $journal): void {
                $user = User::query()->whereKey($journal->target_id)->lockForUpdate()->firstOrFail();
                $credit = (string) $pdo->query('SELECT credit FROM shd_clients WHERE id = 1')->fetchColumn();
                $this->settle($pdo, $user, $journal->id, $credit);
            });
        }
    }

    public function settle(PDO $pdo, User $user, int $journalId, string $creditAfter): void
    {
        $journal = LegacyImportRecord::query()->whereKey($journalId)->lockForUpdate()->firstOrFail();
        if ($journal->state === 'completed') {
            return;
        }
        $payload = $journal->payload;
        $delta = bcsub($creditAfter, (string) $payload['credit_before'], 6);
        if (! LedgerEntry::query()->where('reference_type', 'legacy_api_request')->where('reference_id', $journal->id)->exists() && bccomp($delta, '0', 6) !== 0) {
            $user->balance = bcadd((string) $user->balance, $delta, 6);
            $user->save();
            LedgerEntry::create(['user_id' => $user->id, 'type' => 'legacy_api', 'amount' => $delta, 'balance_after' => $user->balance, 'reference_type' => 'legacy_api_request', 'reference_id' => $journal->id, 'description' => '下游 API 交易同步']);
        }
        $this->mirror->import($pdo, $user);
        $payload['credit_after'] = $creditAfter;
        $payload['delta'] = $delta;
        $payload['completed_at'] = now()->toIso8601String();
        $journal->update(['state' => 'completed', 'payload' => $payload]);
    }
}
