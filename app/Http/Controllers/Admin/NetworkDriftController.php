<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkDriftCheck;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class NetworkDriftController extends Controller
{
    public function index(): View
    {
        $checks = NetworkDriftCheck::query()
            ->with('node')
            ->orderByRaw("FIELD(status, 'drift', 'error', 'clean', 'unknown')")
            ->get();

        return view('admin.network-drift.index', [
            'checks' => $checks,
            'stats' => [
                'clean' => $checks->where('status', 'clean')->count(),
                'drift' => $checks->where('status', 'drift')->count(),
                'error' => $checks->where('status', 'error')->count(),
                'missing' => $checks->sum(fn (NetworkDriftCheck $check): int => count($check->missing ?? [])),
                'extra' => $checks->sum(fn (NetworkDriftCheck $check): int => count($check->extra ?? [])),
            ],
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        Artisan::call('network:check');

        return redirect()
            ->route('admin.network-drift.index')
            ->with('status', trim(Artisan::output()) ?: '网络巡检完成。');
    }

    public function repair(Request $request, NetworkDriftCheck $check, AuditLogger $audit): RedirectResponse
    {
        $node = $check->node;
        if (! $node?->agent_node_id) {
            return back()->withErrors(['repair' => '节点缺少 Agent ID。']);
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

            $result = $client->post('/api/nodes/'.$node->agent_node_id.'/network/repair', [
                'kind' => 'nat',
                'confirm' => true,
            ])->throw()->json();

            $verified = (bool) ($result['after']['verified'] ?? false);
            $check->update([
                'status' => $verified ? 'clean' : 'drift',
                'action' => 'manual_repair',
                'repair_status' => (string) ($result['status'] ?? 'unknown'),
                'repair_result' => $result,
                'repaired_at' => now(),
                'missing' => $verified ? null : $check->missing,
                'extra' => $verified ? null : $check->extra,
                'checked_at' => now(),
            ]);
            $audit->record($request->user('admin'), 'network.repaired', '手动修复网络：'.$node->name, $node, [
                'backup_id' => $result['backup_id'] ?? null,
                'verified' => $verified,
            ]);
        } catch (Throwable $exception) {
            return back()->withErrors(['repair' => '修复失败：'.$exception->getMessage()]);
        }

        return back()->with('status', $check->fresh()->status === 'clean' ? '网络规则已修复并通过回读。' : '修复已执行，但回读仍有差异。');
    }
}
