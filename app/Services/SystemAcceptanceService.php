<?php

namespace App\Services;

use App\Models\CloudNode;
use App\Models\NetworkDriftCheck;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemAcceptanceService
{
    /**
     * @return array<int, array{check: string, status: string, message: string}>
     */
    public function run(): array
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks[] = $this->result('database', 'pass', 'MariaDB 连接正常');
        } catch (Throwable $exception) {
            $checks[] = $this->result('database', 'fail', $exception->getMessage());
        }

        try {
            $key = 'acceptance:'.uniqid();
            Cache::put($key, 'ok', 10);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);
            $checks[] = $this->result('cache', $ok ? 'pass' : 'fail', '数据库缓存读写'.($ok ? '正常' : '异常'));
        } catch (Throwable $exception) {
            $checks[] = $this->result('cache', 'fail', $exception->getMessage());
        }

        try {
            $missing = Order::query()
                ->where('status', 'paid')
                ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'succeeded'))
                ->count();
            $checks[] = $this->result('payments', $missing === 0 ? 'pass' : 'warn', $missing === 0 ? '已支付订单均有成功支付记录' : $missing.' 个已支付订单缺少支付记录');
        } catch (Throwable $exception) {
            $checks[] = $this->result('payments', 'fail', $exception->getMessage());
        }

        try {
            $jobs = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $checks[] = $this->result('queue', $failed === 0 ? 'pass' : 'warn', "待处理 {$jobs}，失败 {$failed}");
        } catch (Throwable $exception) {
            $checks[] = $this->result('queue', 'fail', $exception->getMessage());
        }

        try {
            $drift = NetworkDriftCheck::query()->where('status', 'drift')->count();
            $error = NetworkDriftCheck::query()->where('status', 'error')->count();
            $status = $drift > 0 ? 'warn' : ($error > 0 ? 'warn' : 'pass');
            $checks[] = $this->result('network', $status, "漂移 {$drift}，异常 {$error}");
        } catch (Throwable $exception) {
            $checks[] = $this->result('network', 'fail', $exception->getMessage());
        }

        $disk = (string) config('deviy.upload_disk', 'local');
        try {
            $path = 'acceptance/'.uniqid().'.txt';
            Storage::disk($disk)->put($path, 'ok');
            $ok = Storage::disk($disk)->get($path) === 'ok';
            Storage::disk($disk)->delete($path);
            $checks[] = $this->result('storage', $ok ? 'pass' : 'fail', "附件磁盘 {$disk} ".($ok ? '读写正常' : '读写异常'));
        } catch (Throwable $exception) {
            $checks[] = $this->result('storage', 'fail', $exception->getMessage());
        }

        try {
            $response = Http::timeout(5)->get(rtrim((string) config('deviy.controller_url'), '/').'/health');
            $checks[] = $this->result('controller', $response->successful() ? 'pass' : 'fail', 'Controller HTTP '.$response->status());
        } catch (Throwable $exception) {
            $checks[] = $this->result('controller', 'fail', $exception->getMessage());
        }

        $node = CloudNode::query()->whereNotNull('agent_url')->where('status', 'online')->first();
        if (! $node) {
            $checks[] = $this->result('agent', 'warn', '没有在线 Agent 节点');
        } else {
            try {
                $response = Http::timeout(5)->get(rtrim((string) $node->agent_url, '/').'/health');
                $checks[] = $this->result('agent', $response->successful() ? 'pass' : 'fail', $node->name.' HTTP '.$response->status());
            } catch (Throwable $exception) {
                $checks[] = $this->result('agent', 'fail', $exception->getMessage());
            }
        }

        return $checks;
    }

    /**
     * @return array{check: string, status: string, message: string}
     */
    private function result(string $check, string $status, string $message): array
    {
        return compact('check', 'status', 'message');
    }
}
