<?php

namespace App\Services\Security;

use App\Models\LegacyImportRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LegacyCustomerAuthenticator
{
    /** @param array{email: string, password: string} $credentials */
    public function attempt(#[\SensitiveParameter] array $credentials, bool $remember = false): bool
    {
        if (Auth::attempt($credentials, $remember)) {
            return true;
        }

        $user = DB::transaction(function () use ($credentials): ?User {
            $user = User::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($credentials['email']))])
                ->lockForUpdate()
                ->first();
            if (! $user || ! $user->isActive()) {
                return null;
            }

            $record = LegacyImportRecord::query()
                ->where('source', 'www.deviy.cn')
                ->where('source_table', 'customer_credentials')
                ->where('target_table', 'users')
                ->where('target_id', $user->id)
                ->where('state', 'pending_upgrade')
                ->lockForUpdate()
                ->first();
            if (! $record) {
                return null;
            }

            $payload = $record->payload;
            $boundHash = (string) ($payload['bound_hash'] ?? '');
            if ($boundHash === '' || ! hash_equals($boundHash, (string) $user->getAuthPassword())) {
                $record->update(['state' => 'revoked', 'payload' => []]);

                return null;
            }

            $legacyHash = (string) ($payload['hash'] ?? '');
            if (! preg_match('/^###[a-f0-9]{32}$/D', $legacyHash)) {
                return null;
            }
            $candidate = '###'.md5(md5((string) ($payload['authcode'] ?? '').$credentials['password']));
            if (! hash_equals($legacyHash, $candidate)) {
                return null;
            }

            $user->forceFill(['password' => $credentials['password']])->save();
            $record->update(['state' => 'upgraded', 'payload' => []]);

            return $user;
        });

        if (! $user) {
            return false;
        }
        Auth::login($user, $remember);

        return true;
    }
}
