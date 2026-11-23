<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mail\EmailVerificationService;
use App\Services\Mail\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'qq' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'xf_captcha' => ['required', 'xfCaptcha'],
        ], [
            'name.required' => '请填写昵称。',
            'name.max' => '昵称不能超过 80 个字符。',
            'email.required' => '请填写邮箱地址。',
            'email.email' => '请输入有效的邮箱地址。',
            'password.confirmed' => '两次输入的密码不一致。',
            'password.min' => '密码至少需要 8 位。',
            'xf_captcha.required' => '请完成点选安全验证。',
            'xf_captcha.xfCaptcha' => '点选验证失败或已过期，请重新验证。',
        ]);

        $existing = User::withTrashed()->where('email', $data['email'])->first();
        if ($existing && ! $existing->trashed()) {
            throw ValidationException::withMessages([
                'email' => '该邮箱已经注册，请直接登录或使用其他邮箱。',
            ]);
        }

        $user = DB::transaction(function () use ($data, $existing): User {
            if ($existing) {
                $existing->restore();
                $existing->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'qq' => $data['qq'] ?? null,
                    'company' => $data['company'] ?? null,
                    'password' => $data['password'],
                    'pterodactyl_password' => $data['password'],
                    'status' => 'active',
                    'real_name_status' => 'unverified',
                    'customer_group' => 'default',
                ]);
                $existing->forceFill(['email_verified_at' => null])->save();

                return $existing;
            }

            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'qq' => $data['qq'] ?? null,
                'company' => $data['company'] ?? null,
                'password' => $data['password'],
                'pterodactyl_password' => $data['password'],
                'status' => 'active',
                'real_name_status' => 'unverified',
                'customer_group' => 'default',
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('real_name_prompt_shown');

        app(EmailVerificationService::class)->send($user);
        MailSettings::sendTemplate('account_registered', $user, [
            'login_url' => route('login'),
        ]);

        return redirect()->route('verification.notice')->with('status', '注册成功，请先完成邮箱验证。');
    }
}
