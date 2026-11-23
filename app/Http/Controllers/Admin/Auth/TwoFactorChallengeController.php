<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('admin_2fa_id')) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor');
    }

    public function store(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $adminId = $request->session()->get('admin_2fa_id');
        $admin = Admin::query()->whereKey($adminId)->where('is_active', true)->first();

        if (! $admin || ! $admin->two_factor_secret) {
            $request->session()->forget(['admin_2fa_id', 'admin_2fa_remember']);

            throw ValidationException::withMessages([
                'code' => '验证会话已失效，请重新登录。',
            ]);
        }

        $valid = false;
        if (! empty($data['code'])) {
            $valid = $totp->verify($admin->two_factor_secret, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $valid = $this->consumeRecoveryCode($admin, $data['recovery_code']);
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => '验证码或恢复码无效。',
            ]);
        }

        $remember = (bool) $request->session()->get('admin_2fa_remember', false);
        Auth::guard('admin')->login($admin, $remember);
        $request->session()->forget(['admin_2fa_id', 'admin_2fa_remember']);
        $request->session()->put('admin_2fa_verified_id', $admin->id);
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    private function consumeRecoveryCode(Admin $admin, string $code): bool
    {
        $code = strtoupper(trim($code));
        $codes = $admin->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hashed) {
            if (Hash::check($code, $hashed)) {
                unset($codes[$index]);
                $admin->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
