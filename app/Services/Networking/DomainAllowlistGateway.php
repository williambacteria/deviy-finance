<?php

namespace App\Services\Networking;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DomainAllowlistGateway
{
    public const ENDPOINT = 'https://gb.tianliyun.cn/api/submit';

    /** @return array<string, string|null> */
    public function submit(string $region, string $domain, string $targetIp): array
    {
        $response = Http::asMultipart()->acceptJson()->connectTimeout(5)->timeout(35)
            ->withHeaders(['User-Agent' => 'Deviy-Cloud-Domain-Allowlist/1.0'])->withoutRedirecting()
            ->post(self::ENDPOINT, ['region' => $region, 'domain' => $domain, 'target_ip' => $targetIp]);
        $body = $response->json();
        if (! is_array($body) || ! array_key_exists('ok', $body) || $response->serverError() || $response->redirect() || $response->status() === 429) {
            throw new RuntimeException('机房接口未返回可确认的处理结果。');
        }
        if (! $response->successful() && ! in_array($response->status(), [400, 422], true)) {
            throw new RuntimeException('机房接口暂时不可用。');
        }
        if (isset($body['domain']) && (! is_string($body['domain']) || strtolower($body['domain']) !== $domain)) {
            throw new RuntimeException('接口返回域名与本次申请不一致，需人工核对。');
        }
        if (isset($body['target_ip']) && (! is_string($body['target_ip']) || $body['target_ip'] !== $targetIp)) {
            throw new RuntimeException('接口返回 IP 与本次申请不一致，需人工核对。');
        }
        $code = $this->text($body['code'] ?? '', 64);
        $icpStatus = in_array($body['icp_status'] ?? '', ['filed', 'not_filed', 'error', 'failed', 'unknown'], true) ? $body['icp_status'] : null;
        $providerStatus = $this->text($body['wl_status'] ?? '', 40);
        $okay = ($body['ok'] === true || $body['ok'] === 1) && $response->successful();
        $status = match (true) {
            $code === 'not_filed' || $icpStatus === 'not_filed' => 'rejected',
            ! $okay => 'failed',
            $code === 'exists' && in_array($providerStatus, ['success', 'already_whitelisted'], true) && ! isset($body['target_ip']) => 'unknown',
            $providerStatus === 'success' => 'success',
            $providerStatus === 'already_whitelisted' => 'already_whitelisted',
            $providerStatus === 'submitted' => 'submitted',
            $providerStatus === 'failed' => 'failed',
            $providerStatus === 'skipped' => 'skipped',
            $providerStatus === 'pending' => 'submitted',
            default => 'unknown',
        };
        $message = $this->text($body['message'] ?? '', 500);
        $detail = $this->text($body['wl_response'] ?? '', 500);
        if ($detail !== '' && $detail !== $message) {
            $message = trim($message.' '.$detail);
        }
        if ($message === '') {
            $message = match ($status) {
                'success' => '机房已返回过白成功。',
                'already_whitelisted' => '机房确认该域名已在白名单中。',
                'submitted' => '机房已接收申请，等待审核；这不代表已经过白成功。',
                'rejected' => '域名备案校验未通过，未完成过白。',
                'skipped' => '本次申请已记录，机房未执行过白。',
                'failed' => '机房未完成本次过白，请核对域名、IP 与机房后处理。',
                default => '接口未给出明确的过白结果，请联系支持核对。',
            };
        }

        return ['status' => $status, 'icp_status' => $icpStatus, 'provider_status' => $providerStatus ?: null, 'response_code' => $code ?: null, 'message' => mb_substr($message, 0, 1000)];
    }

    private function text(mixed $value, int $limit): string
    {
        return is_string($value) || is_numeric($value) ? mb_substr(trim(strip_tags((string) $value)), 0, $limit) : '';
    }
}
