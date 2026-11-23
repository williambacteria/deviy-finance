<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\UpstreamSupplier;
use App\Services\Mofang\MofangCartClient;
use App\Support\MofangServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CdnModuleController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user('admin') && in_array($request->user('admin')->role, ['super_admin', 'support'], true), 403);
        } else {
            abort_unless($request->user() && (int) $service->user_id === (int) $request->user()->id, 404);
        }
        abort_unless($service->product?->upstream_source === 'mofang' && MofangServiceType::forService($service) === 'cdn', 404);
        abort_unless($service->status === 'active', 422, '当前产品状态不可提交白名单。');
        $data = $request->validate(['domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/D']]);
        $supplier = UpstreamSupplier::query()->where('driver', 'mofang')->where('status', 'active')->latest('id')->firstOrFail();
        try {
            $response = (new MofangCartClient($supplier->base_url, $supplier->username, (string) $supplier->password, (bool) $supplier->verify_ssl))
                ->submitCdnWhitelist(substr((string) $service->external_ref, 7), mb_strtolower(trim($data['domain'])));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['cdn' => '白名单提交失败：'.$exception->getMessage()]);
        }
        $nodes = array_values(array_filter((array) ($response['nodes'] ?? []), 'is_array'));
        $failed = count(array_filter($nodes, fn (array $node): bool => empty($node['ok'])));

        return back()->with('status', $failed > 0 ? '白名单已提交，部分机房未通过，请查看记录中的处理结果。' : '白名单已提交，请查看下方机房处理记录。');
    }
}
