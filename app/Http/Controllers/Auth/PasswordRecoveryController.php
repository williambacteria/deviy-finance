<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\PasswordRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;

class PasswordRecoveryController extends Controller
{
    public function create(): Response
    {
        return $this->secureView('auth.forgot-password');
    }

    public function store(Request $request, PasswordRecovery $recovery): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'xf_captcha' => ['required', 'xfCaptcha'],
        ], [
            'xf_captcha.required' => '请完成点选安全验证。',
            'xf_captcha.xfCaptcha' => '点选验证失败或已过期，请重新验证。',
        ]);
        $recovery->send($data['email']);

        return back()->withInput($request->only('email'))->with('status', PasswordRecovery::SENT_MESSAGE);
    }

    public function edit(Request $request, string $token): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $token), 404);

        return $this->secureView('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request, PasswordRecovery $recovery): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/D'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'password.confirmed' => '两次输入的密码不一致。',
            'password.min' => '新密码至少需要 10 位，并包含字母和数字。',
            'password.letters' => '新密码需要包含字母。',
            'password.numbers' => '新密码需要包含数字。',
        ]);
        if (! $recovery->reset($data['email'], $data['token'], $data['password'])) {
            return back()->withErrors(['email' => '重设链接无效、已使用或已过期，请重新申请找回密码。'])->withInput($request->only('email'));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', '密码已重设，原登录状态已失效，请使用新密码登录。')->with('email', $data['email']);
    }

    /** @param array<string, mixed> $data */
    private function secureView(string $view, array $data = []): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
