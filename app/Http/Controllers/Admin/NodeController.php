<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudNode;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class NodeController extends Controller
{
    public function index(): View
    {
        $nodes = CloudNode::query()
            ->withCount('services')
            ->orderBy('region')
            ->orderBy('name')
            ->get();

        return view('admin.nodes.index', compact('nodes'));
    }

    public function show(CloudNode $node): View
    {
        $node->load(['services' => fn ($query) => $query->with('user')->latest()->limit(10), 'ipPools']);

        return view('admin.nodes.show', compact('node'));
    }

    public function toggleMaintenance(Request $request, CloudNode $node, AuditLogger $audit): RedirectResponse
    {
        $node->update(['is_maintenance' => ! $node->is_maintenance]);
        $audit->record($request->user('admin'), 'node.maintenance_toggled', '节点维护状态切换：'.$node->name, $node);

        return back()->with('status', '节点维护状态已更新。');
    }

    public function networkPolicy(Request $request, CloudNode $node, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'network_policy' => ['required', 'in:alert,auto_repair'],
        ]);

        $node->update(['network_policy' => $data['network_policy']]);
        $audit->record($request->user('admin'), 'node.network_policy', '节点网络策略：'.$data['network_policy'], $node);

        return back()->with('status', '网络巡检策略已更新。');
    }

    public function diagnose(Request $request, CloudNode $node, AuditLogger $audit): RedirectResponse
    {
        if (! $node->agent_node_id) {
            return back()->withErrors(['diagnose' => '该节点未配置 Agent Node ID。']);
        }

        try {
            $client = Http::baseUrl(rtrim((string) config('deviy.controller_url'), '/'))
                ->acceptJson()
                ->timeout((int) config('deviy.timeout', 30));

            $token = (string) config('deviy.controller_token');
            if ($token !== '') {
                $client = $client->withToken($token);
            }

            $options = [];
            $ca = (string) config('deviy.controller_ca');
            $cert = (string) config('deviy.controller_cert');
            $key = (string) config('deviy.controller_key');
            if ($ca !== '') {
                $options['verify'] = $ca;
            }
            if ($cert !== '' && $key !== '') {
                $options['cert'] = $cert;
                $options['ssl_key'] = $key;
            }
            if ($options !== []) {
                $client = $client->withOptions($options);
            }

            $diagnostics = $client->get('/api/nodes/'.$node->agent_node_id.'/diagnostics')->throw()->json();
            $node->update(['diagnostics' => $diagnostics, 'diagnosed_at' => now()]);
            $audit->record($request->user('admin'), 'node.diagnosed', '节点预检：'.$node->name, $node, [
                'status' => $diagnostics['status'] ?? 'unknown',
            ]);
        } catch (Throwable $exception) {
            return back()->withErrors(['diagnose' => '预检失败：'.$exception->getMessage()]);
        }

        return back()->with('status', '节点预检完成。');
    }
}
