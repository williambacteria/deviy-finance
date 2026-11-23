<?php

namespace App\Services\RealName;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PhoneThreeFactorVerifier
{
    /**
     * @return array{status: string, message: string}
     */
    public function verify(string $name, string $idNumber, string $phone): array
    {
        $appcode = (string) config('services.aliyun_market.appcode');
        if ($appcode === '') {
            return ['status' => 'manual', 'message' => '实名接口未配置，转人工审核。'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'APPCODE '.$appcode,
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ])->asForm()->timeout(20)->post((string) config('services.aliyun_market.endpoint'), [
                'name' => $name,
                'id_number' => $idNumber,
                'phone_number' => $phone,
            ]);

            $payload = $response->json();
            if (! is_array($payload)) {
                Log::warning('Aliyun phone verification returned non-json', ['status' => $response->status()]);

                return ['status' => 'manual', 'message' => '实名接口返回异常，转人工审核。'];
            }

            $state = (int) ($payload['state'] ?? 0);
            $message = (string) ($payload['result_message'] ?? '');

            return match ($state) {
                1 => ['status' => 'verified', 'message' => $message ?: '姓名、身份证号和手机号核验一致。'],
                2 => ['status' => 'mismatch', 'message' => $message ?: '姓名、身份证号和手机号不一致。'],
                4 => ['status' => 'manual', 'message' => $message ?: '运营商暂无该号码记录，转人工审核。'],
                default => ['status' => 'manual', 'message' => $message ?: '实名接口暂时无法确认，转人工审核。'],
            };
        } catch (Throwable $exception) {
            Log::warning('Aliyun phone verification failed', ['error' => $exception->getMessage()]);

            return ['status' => 'manual', 'message' => '实名接口暂时不可用，转人工审核。'];
        }
    }
}
