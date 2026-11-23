<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => '管理员邮箱或密码不正确。',
            ]);
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            throw ValidationException::withMessages([
                'email' => '该管理员账户已停用。',
            ]);
        }

        if ($admin->two_factor_confirmed_at) {
            Auth::guard('admin')->logout();
            $request->session()->put([
                'admin_2fa_id' => $admin->id,
                'admin_2fa_remember' => $request->boolean('remember'),
            ]);
            $request->session()->regenerate();

            return redirect()->route('admin.two-factor.create');
        }

        $request->session()->put('admin_2fa_verified_id', $admin->id);
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
