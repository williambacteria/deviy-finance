<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudNode;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Provisioning\ControllerGateway;
use App\Services\Provisioning\ProviderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ControllerIntegrationController extends Controller
{
    public function index(ControllerGateway $gateway, ProviderProfile $profiles): View
    {
        $nodes = [];
        $error = null;

        try {
            $nodes = $gateway->nodes();
        } catch (Throwable $exception) {
            report($exception);
            $error = '无法连接主控：'.$exception->getMessage();
        }

        $profilesByNode = [];
        $overviews = [];
        foreach ($nodes as $node) {
            $nodeId = (string) ($node['id'] ?? '');
            $profilesByNode[$nodeId] = $profiles->for($node['provider'] ?? null);
            try {
                $overviews[$nodeId] = $gateway->overview($nodeId);
            } catch (Throwable $exception) {
                $overviews[$nodeId] = ['error' => $exception->getMessage()];
            }
        }

        return view('admin.controller.index', compact('nodes', 'profilesByNode', 'overviews', 'error'));
    }

    public function show(string $node, ControllerGateway $gateway, ProviderProfile $profiles): View
    {
        try {
            $nodeData = $gateway->node($node);
        } catch (Throwable $exception) {
            report($exception);
            abort(502, '主控连接失败：'.$exception->getMessage());
        }

        abort_if($nodeData === null, 404, '主控节点不存在。');

        $vmError = null;
        $imageError = null;
        $poolError = null;
        $vms = [];
        $images = [];
        $pools = [];

        try {
            $vms = $gateway->virtualMachines($node);
        } catch (Throwable $exception) {
            report($exception);
            $vmError = $exception->getMessage();
        }

        try {
            $images = $gateway->images($node);
        } catch (Throwable $exception) {
            report($exception);
            $imageError = $exception->getMessage();
        }

        try {
            $pools = $gateway->ipPools($node);
        } catch (Throwable $exception) {
            report($exception);
            $poolError = $exception->getMessage();
        }

        $profile = $profiles->for($nodeData['provider'] ?? null);
        $overview = [];
        try {
            $overview = $gateway->overview($node);
        } catch (Throwable $exception) {
            report($exception);
        }
        $localNode = CloudNode::query()->where('agent_node_id', $node)->with(['services' => fn ($query) => $query->latest()->limit(20)])->first();
        $localServices = $localNode?->services ?? collect();
        $customers = User::query()->orderBy('email')->limit(300)->get(['id', 'name', 'email']);

        return view('admin.controller.show', compact('nodeData', 'profile', 'overview', 'vms', 'images', 'pools', 'vmError', 'imageError', 'poolError', 'localNode', 'localServices', 'customers'));
    }

    public function create(Request $request, string $node, ControllerGateway $gateway, ProviderProfile $profiles): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:63', 'regex:/^[A-Za-z][A-Za-z0-9.-]*$/'],
            'cores' => ['required', 'integer', 'min:1', 'max:128'],
            'memory_gb' => ['required', 'integer', 'min:1', 'max:1024'],
            'disk_gb' => ['required', 'integer', 'min:10', 'max:4096'],
            'data_disk_gb' => ['nullable', 'integer', 'min:0', 'max:4096'],
            'ostype' => ['nullable', 'string', 'max:32'],
            'image_id' => ['nullable', 'string', 'max:80'],
            'ip_pool_id' => ['nullable', 'string', 'max:80'],
            'network_mode' => ['nullable', 'in:public,private'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'start' => ['nullable', 'boolean'],
        ]);

        try {
            $nodeData = $gateway->node($node);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['controller' => '主控连接失败：'.$exception->getMessage()])->withInput();
        }

        if ($nodeData === null) {
            return back()->withErrors(['controller' => '主控节点不存在。'])->withInput();
        }

        $profile = $profiles->for($nodeData['provider'] ?? null);
        if (! $profile['virtual_machine']) {
            return back()->withErrors(['controller' => $profile['note']])->withInput();
        }

        $payload = [
            'name' => $data['name'],
            'cores' => (int) $data['cores'],
            'memory_mb' => (int) $data['memory_gb'] * 1024,
            'disk_gb' => (int) $data['disk_gb'],
            'data_disk_gb' => (int) ($data['data_disk_gb'] ?? 0),
            'ostype' => $data['ostype'] ?? 'l26',
            'start' => (bool) ($data['start'] ?? false),
            'network_mode' => $data['network_mode'] ?? 'private',
        ];

        if (! empty($data['image_id'])) {
            $payload['image_id'] = $data['image_id'];
        }
        if (! empty($data['ip_pool_id'])) {
            $payload['ip_pool_id'] = $data['ip_pool_id'];
        }
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        try {
            $vm = $gateway->createVirtualMachine($node, $payload);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['controller' => '创建失败：'.$exception->getMessage()])->withInput();
        }

        $vmName = (string) ($vm['name'] ?? $data['name']);

        return redirect()
            ->route('admin.controller.show', $node)
            ->with('status', '主控已接受创建任务：'.$vmName);
    }

    public function storeNode(Request $request, ControllerGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'name' => ['required', 'string', 'max:80'],
            'provider' => ['required', 'in:pve,hyperv,kvm,baremetal'],
            'region' => ['nullable', 'string', 'max:80'],
            'url' => ['required', 'url', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
            'node_ip' => ['nullable', 'ip'],
            'public_bridge' => ['nullable', 'string', 'max:64'],
            'private_bridge' => ['nullable', 'string', 'max:64'],
            'port_min' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'port_max' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'dns1' => ['nullable', 'ip'],
            'dns2' => ['nullable', 'ip'],
            'exclude_ports' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = array_filter($data, fn ($value): bool => $value !== null && $value !== '');

        try {
            $gateway->createHost($payload);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['controller' => '添加主控失败：'.$exception->getMessage()])->withInput();
        }

        $url = parse_url($data['url']);
        CloudNode::query()->updateOrCreate(
            ['agent_node_id' => $data['id']],
            [
                'name' => $data['name'],
                'agent_url' => $data['url'],
                'agent_token' => $data['token'] ?? null,
                'agent_mode' => 'controller',
                'provider' => $data['provider'],
                'host' => $url['host'] ?? $data['url'],
                'port' => (int) ($url['port'] ?? ($data['provider'] === 'pve' ? 8006 : 8443)),
                'region' => $data['region'] ?? 'default',
                'status' => 'offline',
            ],
        );

        return back()->with('status', '主控已添加：'.$data['name']);
    }

    public function createBareMetalService(Request $request, string $node, ControllerGateway $gateway, ProviderProfile $profiles, AuditLogger $audit): RedirectResponse
    {
        try {
            $nodeData = $gateway->node($node);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['controller' => '主控连接失败：'.$exception->getMessage()])->withInput();
        }

        if ($nodeData === null) {
            return back()->withErrors(['controller' => '主控节点不存在。'])->withInput();
        }

        $profile = $profiles->for($nodeData['provider'] ?? null);
        if ($profile['virtual_machine']) {
            return back()->withErrors(['controller' => '该节点是虚拟化节点，请使用创建虚拟机。'])->withInput();
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:120'],
            'ip_address' => ['nullable', 'ip'],
            'cpu' => ['nullable', 'integer', 'min:1', 'max:256'],
            'memory_gb' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'disk_gb' => ['nullable', 'integer', 'min:1', 'max:65536'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $localNode = CloudNode::query()->where('agent_node_id', $node)->first();
        if (! $localNode) {
            return back()->withErrors(['controller' => '财务侧还没有对应节点记录，请先在节点映射中创建。'])->withInput();
        }

        $service = Service::create([
            'service_no' => 'BM-'.strtoupper(Str::random(8)),
            'user_id' => $data['user_id'],
            'node_id' => $localNode->id,
            'name' => $data['name'],
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'auto_renew' => false,
            'region' => $nodeData['region'] ?? $localNode->region,
            'ip_address' => $data['ip_address'] ?? null,
            'specs' => [
                'cpu' => $data['cpu'] ?? 1,
                'memory_gb' => $data['memory_gb'] ?? 1,
                'disk_gb' => $data['disk_gb'] ?? 1,
            ],
            'expires_at' => $data['expires_at'] ?? null,
            'provision_status' => 'succeeded',
            'external_ref' => 'baremetal:'.$node,
        ]);

        $audit->record($request->user('admin'), 'controller.baremetal_service_created', '裸金属服务登记：'.$service->service_no, $service);

        return back()->with('status', '裸金属服务已登记：'.$service->service_no);
    }

    public function action(Request $request, string $node, string $vmid, ControllerGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:start,stop,reboot,force-stop,delete'],
        ]);

        try {
            $gateway->action($node, $vmid, $data['action']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['controller' => '操作失败：'.$exception->getMessage()]);
        }

        return back()->with('status', '主控操作已提交：'.$data['action']);
    }
}
