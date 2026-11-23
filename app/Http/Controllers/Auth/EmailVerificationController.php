<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mail\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('console.dashboard');
        }

        return view('auth.verify-email');
    }

    public function verifyCode(Request $request, EmailVerificationService $verification): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! $verification->verify($request->user(), $data['code'])) {
            return back()->withErrors(['code' => '验证码错误或已过期，请重新获取。']);
        }

        return redirect()->route('console.dashboard')->with('status', '邮箱验证成功，欢迎使用 Deviy Cloud。');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->email), $hash), 403);
        abort_unless($request->hasValidSignature(), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (! Auth::check()) {
            Auth::login($user);
        }

        return redirect()->route('console.dashboard')->with('status', '邮箱验证成功，欢迎使用 Deviy Cloud。');
    }

    public function send(Request $request, EmailVerificationService $verification): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('console.dashboard');
        }

        $sent = $verification->send($user);

        return back()->with(
            $sent ? 'status' : 'error',
            $sent ? '验证码已发送，请查收邮箱。' : '验证码发送失败，请稍后重试或联系客服。'
        );
    }
}
