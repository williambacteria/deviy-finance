<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Services\Pterodactyl\PterodactylProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('console.settings', [
            'user' => $request->user(),
            'notificationTypes' => [
                'service_due' => '实例到期提醒',
                'ticket_reply' => '工单回复',
                'payment_succeeded' => '支付成功',
                'service_suspended' => '实例停机提醒',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'qq' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:120'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ]);

        $user->update($data);

        return back()->with('status', '资料已更新。');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => '当前密码不正确。']);
        }

        $request->user()->update(['password' => $data['password'], 'pterodactyl_password' => $data['password']]);

        if ($request->user()->services()->whereHas('product', fn ($query) => $query->where('upstream_source', 'pterodactyl'))->exists()) {
            try {
                app(PterodactylProvisioner::class)->syncUserPassword($request->user(), $data['password']);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('status', '密码已更新。');
    }

    public function notifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['nullable', 'boolean'],
        ]);

        foreach (['email', 'sms'] as $channel) {
            foreach (['service_due', 'ticket_reply', 'payment_succeeded', 'service_suspended'] as $type) {
                NotificationPreference::updateOrCreate(
                    ['user_id' => $request->user()->id, 'channel' => $channel, 'type' => $type],
                    ['enabled' => (bool) ($data['preferences'][$channel.'.'.$type] ?? false)],
                );
            }
        }

        return back()->with('status', '通知偏好已保存。');
    }
}
