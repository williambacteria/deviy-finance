<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Payments\WechatPaySettings;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WechatPaymentSettingsController extends Controller
{
    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user('admin')?->isSuperAdmin(), 403);

        try {
            $data = $request->validate([
                'enabled' => ['required', 'boolean'],
                'mch_id' => ['nullable', 'regex:/^[0-9]{6,20}$/D'],
                'app_id' => ['nullable', 'regex:/^wx[a-zA-Z0-9]{16}$/D'],
                'serial_no' => ['nullable', 'regex:/^[a-fA-F0-9]{1,64}$/D'],
                'api_v3_key' => ['nullable', 'string', 'size:32', 'regex:/^[\x21-\x7E]{32}$/D'],
                'platform_key_id' => ['nullable', 'regex:/^(?:PUB_KEY_ID_[A-Za-z0-9_]{8,120}|[A-Fa-f0-9]{20,64})$/D'],
                'merchant_private_file' => ['nullable', 'file', 'max:32'],
                'merchant_certificate_file' => ['nullable', 'file', 'max:32'],
                'platform_public_file' => ['nullable', 'file', 'max:32'],
                'platform_public_key' => ['nullable', 'string', 'max:16000'],
            ], [], ['api_v3_key' => 'API v3 密钥', 'mch_id' => '商户号', 'app_id' => 'AppID', 'serial_no' => '商户证书序列号', 'platform_key_id' => '微信支付公钥 ID']);

            $current = WechatPaySettings::configuration();
            $values = [];
            foreach (['mch_id', 'app_id', 'serial_no', 'platform_key_id'] as $field) {
                $values[$field] = trim((string) ($data[$field] ?? ''));
            }
            $values['enabled'] = $request->boolean('enabled');
            $values['notify_url'] = route('payments.wechat.notify');
            $values['api_v3_key'] = (string) ($data['api_v3_key'] ?? '') !== '' ? $data['api_v3_key'] : (string) ($current['api_v3_key'] ?? '');
            $values['private_key'] = (string) ($current['private_key'] ?? '');
            $values['platform_public_key'] = trim((string) ($data['platform_public_key'] ?? '')) ?: (string) ($current['platform_public_key'] ?? '');

            if ($request->hasFile('merchant_private_file')) {
                $values['private_key'] = trim((string) file_get_contents($request->file('merchant_private_file')->getRealPath()));
            }
            $privatePem = WechatPaySettings::readKey($values['private_key']);
            if ($privatePem !== '') {
                $private = @openssl_pkey_get_private($privatePem);
                $details = $private !== false ? openssl_pkey_get_details($private) : false;
                if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] !== 2048) {
                    throw ValidationException::withMessages(['merchant_private_file' => '请上传有效的 RSA 2048 位商户 API 私钥 PEM 文件。']);
                }
            }

            $certificateUploaded = $request->hasFile('merchant_certificate_file');
            if ($certificateUploaded) {
                $certificatePem = (string) file_get_contents($request->file('merchant_certificate_file')->getRealPath());
                $certificate = @openssl_x509_parse($certificatePem);
                if (! $certificate || empty($certificate['serialNumberHex'])) {
                    throw ValidationException::withMessages(['merchant_certificate_file' => '无法读取商户 API 证书序列号，请上传 apiclient_cert.pem。']);
                }
                if ($certificate['validTo_time_t'] <= time() || $certificate['validFrom_time_t'] > time()) {
                    throw ValidationException::withMessages(['merchant_certificate_file' => '商户证书已过期或尚未生效。']);
                }
                $certificateMchId = (string) ($certificate['subject']['CN'] ?? '');
                if ($certificateMchId !== '' && $certificateMchId !== $values['mch_id']) {
                    throw ValidationException::withMessages(['merchant_certificate_file' => '证书中的商户号与填写的商户号不一致。']);
                }
                if ($privatePem !== '' && ! openssl_x509_check_private_key($certificatePem, $privatePem)) {
                    throw ValidationException::withMessages(['merchant_private_file' => '商户私钥与所上传的商户证书不匹配。']);
                }
                $values['serial_no'] = strtoupper($certificate['serialNumberHex']);
            } elseif ($request->hasFile('merchant_private_file') && (string) ($current['serial_no'] ?? '') !== '' && strtoupper($values['serial_no']) === strtoupper((string) $current['serial_no'])) {
                throw ValidationException::withMessages(['merchant_certificate_file' => '替换商户私钥时请同时上传配套商户证书，避免密钥与序列号不匹配。']);
            }

            if ($request->hasFile('platform_public_file')) {
                $values['platform_public_key'] = trim((string) file_get_contents($request->file('platform_public_file')->getRealPath()));
            }
            $platformPem = WechatPaySettings::readKey($values['platform_public_key']);
            if ($platformPem !== '') {
                if (str_contains($platformPem, 'PRIVATE KEY')) {
                    throw ValidationException::withMessages(['platform_public_file' => '此处需要微信支付公钥，不能上传私钥。']);
                }
                $public = @openssl_pkey_get_public($platformPem);
                $details = $public !== false ? openssl_pkey_get_details($public) : false;
                if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] !== 2048) {
                    throw ValidationException::withMessages(['platform_public_file' => '请提供有效的微信支付 RSA 2048 位公钥或平台证书。']);
                }
                if (str_contains($platformPem, 'BEGIN CERTIFICATE')) {
                    $platformCertificate = @openssl_x509_parse($platformPem);
                    if (! $platformCertificate || $platformCertificate['validTo_time_t'] <= time()) {
                        throw ValidationException::withMessages(['platform_public_file' => '微信支付平台证书无效或已过期。']);
                    }
                    $values['platform_key_id'] = strtoupper($platformCertificate['serialNumberHex']);
                }
            }
            if ($values['enabled']) {
                foreach (['app_id', 'mch_id', 'serial_no', 'private_key', 'api_v3_key', 'platform_key_id', 'platform_public_key'] as $field) {
                    if (($values[$field] ?? '') === '') {
                        throw ValidationException::withMessages(['enabled' => '请先填写商户号、AppID、商户证书序列号、私钥、API v3 密钥及微信支付公钥和公钥 ID，再开启微信支付。']);
                    }
                }
            }

            DB::transaction(function () use ($values, $current, $request, $audit): void {
                foreach ($values as $field => $value) {
                    $secret = in_array($field, ['private_key', 'api_v3_key'], true);
                    if ($secret && $value === ($current[$field] ?? '') && Setting::where('group_name', 'payment')->where('key', 'payment.wechat_'.$field)->exists()) {
                        continue;
                    }
                    $stored = $field === 'enabled' ? ($value ? '1' : '0') : (string) $value;
                    if ($secret && $stored !== '') {
                        $stored = Crypt::encryptString($stored);
                    }
                    Setting::updateOrCreate(['group_name' => 'payment', 'key' => 'payment.wechat_'.$field], ['value' => $stored, 'type' => $secret ? 'encrypted' : 'string']);
                }
                $audit->record($request->user('admin'), 'payments.wechat.updated', '更新微信支付配置', null, [
                    'enabled' => $values['enabled'], 'mch_id' => $values['mch_id'], 'app_id' => $values['app_id'],
                    'private_key_changed' => $request->hasFile('merchant_private_file'), 'api_v3_key_changed' => (string) $request->input('api_v3_key', '') !== '',
                ]);
            });
            WechatPaySettings::apply();

            return redirect()->route('admin.settings.index', ['section' => 'payment'])->with('status', '微信支付配置已保存；密钥不会回显，留空会保留原配置。');
        } catch (ValidationException $exception) {
            return redirect()->route('admin.settings.index', ['section' => 'payment'])->withErrors($exception->errors())->withInput($request->only(['enabled', 'mch_id', 'app_id', 'serial_no', 'platform_key_id', 'platform_public_key']));
        }
    }
}
