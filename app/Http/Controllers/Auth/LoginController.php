<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\LegacyCustomerAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, LegacyCustomerAuthenticator $authenticator): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'xf_captcha' => ['required', 'xfCaptcha'],
        ], [
            'xf_captcha.required' => '请完成点选安全验证。',
            'xf_captcha.xfCaptcha' => '点选验证失败或已过期，请重新验证。',
        ]);

        if (! $authenticator->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => '邮箱或密码不正确。',
            ]);
        }

        if (! $request->user()->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => '该账户已被暂停，请联系客服。',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('real_name_prompt_shown');
        $request->user()->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
            'pterodactyl_password' => $credentials['password'],
        ])->save();

        return redirect()->intended(route('console.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
