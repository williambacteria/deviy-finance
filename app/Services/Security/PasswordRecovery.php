<?php

namespace App\Services\Security;

use App\Models\LegacyImportRecord;
use App\Models\User;
use App\Services\Mail\MailSettings;
use App\Services\Pterodactyl\PterodactylProvisioner;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class PasswordRecovery
{
    public const SENT_MESSAGE = '如果该邮箱对应可用账户，我们会发送密码重设邮件。请查看收件箱和垃圾邮件；短时间内请勿重复申请。';

    public function send(string $email): void
    {
        $identity = hash('sha256', mb_strtolower(trim($email)));
        if (RateLimiter::tooManyAttempts('password-recovery:hour:'.$identity, 5)
            || RateLimiter::tooManyAttempts('password-recovery:minute:'.$identity, 1)) {
            return;
        }
        RateLimiter::hit('password-recovery:hour:'.$identity, 3600);
        RateLimiter::hit('password-recovery:minute:'.$identity, 60);
        $lock = Cache::lock('password-recovery:'.$identity, 120);
        if (! $lock->get()) {
            return;
        }
        try {
            $broker = Password::broker('users');
            $status = $broker->sendResetLink(['email' => $email, 'status' => 'active', 'deleted_at' => null], function (User $recipient, string $token) use ($broker): string {
                $base = rtrim((string) config('app.url'), '/');
                $url = $base.'/reset-password/'.rawurlencode($token).'?'.http_build_query(['email' => $recipient->email]);
                $sent = MailSettings::sendTemplate('password_reset', $recipient, [
                    'reset_url' => $url,
                    'expires_minutes' => (string) config('auth.passwords.users.expire', 60),
                ]);
                if (! $sent) {
                    $broker->deleteToken($recipient);
                    Log::warning('Password recovery email could not be delivered', ['user_id' => $recipient->id]);
                    return Password::RESET_THROTTLED;
                }
                return Password::RESET_LINK_SENT;
            });
            if ($status === Password::RESET_THROTTLED) {
                Log::warning('Password recovery email delivery failed or was throttled');
            }
        } catch (Throwable) {
            Log::warning('Password recovery request could not be completed');
        } finally {
            $lock->release();
        }
    }

    public function reset(string $email, string $token, #[\SensitiveParameter] string $password): bool
    {
        $identity = hash('sha256', mb_strtolower(trim($email)));
        return Cache::lock('password-recovery:'.$identity, 120)->block(3, function () use ($email, $token, $password): bool {
            $user = $this->findUser($email);
            if (! $user || ! $user->isActive()) {
                return false;
            }
            $result = DB::transaction(function () use ($user, $token, $password): string {
                return Password::broker('users')->reset([
                    'email' => $user->email, 'status' => 'active', 'deleted_at' => null, 'token' => $token,
                    'password' => $password, 'password_confirmation' => $password,
                ], function (User $account, #[\SensitiveParameter] string $newPassword): void {
                    $account->forceFill([
                        'password' => $newPassword,
                        'pterodactyl_password' => $newPassword,
                        'remember_token' => Str::random(60),
                    ])->save();
                    LegacyImportRecord::where('source', 'www.deviy.cn')->where('source_table', 'customer_credentials')
                        ->where('target_table', 'users')->where('target_id', $account->id)->where('state', 'pending_upgrade')
                        ->get()->each(fn ($record) => $record->update(['state' => 'revoked', 'payload' => []]));
                    if (config('session.driver') === 'database') {
                        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                            ->where('user_id', $account->id)->delete();
                    }
                    event(new PasswordReset($account));
                });
            });
            if ($result !== Password::PASSWORD_RESET) {
                return false;
            }
            if ($user->services()->whereHas('product', fn ($query) => $query->where('upstream_source', 'pterodactyl'))->exists()) {
                try {
                    app(PterodactylProvisioner::class)->syncUserPassword($user->fresh(), $password);
                } catch (Throwable) {
                    Log::warning('Panel password synchronization pending after account recovery', ['user_id' => $user->id]);
                }
            }
            MailSettings::sendTemplate('password_changed', $user->fresh(), [
                'changed_at' => now()->format('Y-m-d H:i').'（北京时间）',
                'login_url' => rtrim((string) config('app.url'), '/').route('login', [], false),
            ]);

            return true;
        });
    }

    private function findUser(string $email): ?User
    {
        return User::whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($email))])->first();
    }
}
