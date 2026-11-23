<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function show(Request $request, Totp $totp): View
    {
        $admin = $request->user('admin');
        $setupSecret = null;
        $provisioningUri = null;

        if (! $admin->two_factor_confirmed_at) {
            $setupSecret = $request->session()->get('admin_2fa_setup_secret');
            if (! is_string($setupSecret) || $setupSecret === '') {
                $setupSecret = $totp->generateSecret();
                $request->session()->put('admin_2fa_setup_secret', $setupSecret);
            }
            $provisioningUri = $totp->provisioningUri($admin->email, $setupSecret);
        }

        return view('admin.security.show', compact('admin', 'setupSecret', 'provisioningUri'));
    }

    public function enable(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        $admin = $request->user('admin');
        $secret = $request->session()->get('admin_2fa_setup_secret');
        if (! is_string($secret) || $secret === '' || ! $totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => '验证码不正确，请确认手机时间与验证器一致。',
            ]);
        }

        $recoveryCodes = collect(range(1, 8))
            ->map(fn (): string => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('admin_2fa_setup_secret');
        $request->session()->put('admin_2fa_verified_id', $admin->id);
        $request->session()->flash('recovery_codes', $recoveryCodes);
        $audit->record($admin, 'admin.two_factor_enabled', '管理员启用双重验证', $admin);

        return redirect()->route('admin.security.show')->with('status', '双重验证已启用，请保存恢复码。');
    }

    public function disable(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $admin = $request->user('admin');
        if (! Hash::check($data['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'password' => '密码不正确。',
            ]);
        }

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('admin_2fa_setup_secret');
        $request->session()->put('admin_2fa_verified_id', $admin->id);
        $audit->record($admin, 'admin.two_factor_disabled', '管理员关闭双重验证', $admin);

        return redirect()->route('admin.security.show')->with('status', '双重验证已关闭。');
    }
}
