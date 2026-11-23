<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PluginController extends Controller
{
    public function index(): View
    {
        $plugins = Plugin::query()->where('type', '!=', 'payment')->whereNotIn('code', ['alipay', 'wechat', 'wechat_pay', 'wepay', 'payment'])->orderBy('type')->orderBy('name')->get();
        $paymentData = app(PaymentSettingsController::class)->index()->getData();
        $paymentChannels = ['alipay' => (bool) $paymentData['enabled'], 'wechat' => filter_var($paymentData['wechat']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)];

        return view('admin.plugins.index', compact('plugins', 'paymentChannels'));
    }

    public function toggle(Request $request, Plugin $plugin, AuditLogger $audit): RedirectResponse
    {
        if ($plugin->type === 'payment' || in_array($plugin->code, ['alipay', 'wechat', 'wechat_pay', 'wepay', 'payment'], true)) {
            return redirect()->route('admin.settings.index', ['section' => 'payment'])->with('status', '支付模块统一在支付渠道中配置，渠道状态以实际支付设置为准。');
        }
        $plugin->update(['is_enabled' => ! $plugin->is_enabled]);
        $audit->record($request->user('admin'), 'plugin.toggled', ($plugin->is_enabled ? '启用' : '停用').'插件：'.$plugin->name, $plugin);

        return back()->with('status', '插件状态已更新。');
    }
}
