<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Mofang\Contracts\MofangGateway;
use App\Support\MofangServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BareMetalMaintenanceController extends Controller
{
    public function store(Request $request, Service $service, MofangGateway $gateway): RedirectResponse
    {
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user('admin') && in_array($request->user('admin')->role, ['super_admin', 'support'], true), 403);
        } else {
            abort_unless($request->user() && (int) $request->user()->id === (int) $service->user_id, 404);
        }
        abort_unless($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) === 'bare_metal', 404);
        abort_unless(in_array($service->status, ['active', 'stopped'], true), 422, '当前实例状态不可执行维护操作。');
        $input = $request->validate(['operation' => ['required', 'in:bmc,rescue'], 'confirm_downtime' => ['accepted'], 'system' => ['required_if:operation,rescue', 'nullable', 'in:1,2']]);
        $id = substr((string) $service->external_ref, 7);
        try {
            $detail = $gateway->fetchService($id);
            if ((string) data_get($detail, 'data.host_data.id') !== $id || data_get($detail, 'data.dcim.auth.'.$input['operation']) !== 'on') {
                return back()->withErrors(['maintenance' => '该实例暂不支持此维护操作。']);
            }
            $gateway->performServiceAction($id, $input['operation'], ['system' => $input['system'] ?? null]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['maintenance' => '维护操作未完成：'.str_replace(['魔方上游', '魔方', '上游'], '', $exception->getMessage())]);
        }
        Log::info('Bare metal maintenance submitted', ['service_id' => $service->id, 'operation' => $input['operation'], 'actor_id' => $request->routeIs('admin.*') ? $request->user('admin')->id : $request->user()->id, 'admin' => $request->routeIs('admin.*')]);

        return back()->with('status', $input['operation'] === 'bmc' ? 'BMC 重置命令已提交，请稍后刷新。' : '救援系统启动命令已提交，请稍后通过远程控制台连接。');
    }
}
