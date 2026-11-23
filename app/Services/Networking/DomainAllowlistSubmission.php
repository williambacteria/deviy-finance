<?php

namespace App\Services\Networking;

use App\Models\DomainAllowlistRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class DomainAllowlistSubmission
{
    public function __construct(private readonly DomainAllowlistGateway $gateway) {}

    /** @param array{region: string, domain: string, target_ip: string} $data */
    public function submit(User $user, array $data, bool $resubmit = false): DomainAllowlistRequest
    {
        $fingerprint = hash('sha256', implode('|', $data));

        return Cache::lock('domain-allowlist:'.$fingerprint, 90)->block(2, function () use ($user, $data, $resubmit): DomainAllowlistRequest {
            $record = DomainAllowlistRequest::firstOrNew(['user_id' => $user->id, 'region' => $data['region'], 'domain' => $data['domain'], 'target_ip' => $data['target_ip']]);
            if ($record->exists && (! $resubmit || ! $record->canResubmit())) {
                return $record;
            }
            $limitKey = 'domain-allowlist:daily:'.$user->id;
            if (RateLimiter::tooManyAttempts($limitKey, 30)) {
                throw ValidationException::withMessages(['domain' => '今天的提交次数已达上限，请稍后再试或联系支持。']);
            }
            RateLimiter::hit($limitKey, 86400);
            $record->fill(['status' => 'processing', 'icp_status' => null, 'provider_status' => null, 'response_code' => null, 'message' => '正在提交至机房，请稍候查看处理结果。', 'attempts' => ($record->attempts ?? 0) + 1, 'submitted_at' => now()])->save();
            try {
                $result = $this->gateway->submit($data['region'], $data['domain'], $data['target_ip']);
                $record->update($result);
            } catch (Throwable $exception) {
                $record->update(['status' => 'unknown', 'message' => '接口超时或返回异常，本次结果尚未确认。请先联系支持核对，不要连续重复提交。']);
                Log::warning('Domain allowlist result unconfirmed', ['request_id' => $record->id, 'region' => $data['region'], 'error_type' => get_class($exception)]);
            }

            return $record->fresh();
        });
    }
}
