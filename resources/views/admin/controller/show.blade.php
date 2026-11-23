@extends('layouts.admin')

@section('title', '主控 · '.($nodeData['name'] ?? $nodeData['id']))
@section('page-title', '主控对接')

@section('content')
    @php
        $statusLabels = [
            'running' => '运行中',
            'stopped' => '已停止',
            'paused' => '已暂停',
            'crashed' => '已崩溃',
            'shutdown' => '已关机',
            'online' => '在线',
            'offline' => '离线',
        ];
        $vmStatus = fn ($status) => $statusLabels[strtolower((string) $status)] ?? ($status ?: '未知');
    @endphp

    <div class="page-head">
        <div>
            <div class="small muted"><a href="{{ route('admin.controller.index') }}">主控对接</a> / {{ $nodeData['id'] ?? '' }}</div>
            <h1>{{ $nodeData['name'] ?? $nodeData['id'] }}</h1>
            <p>{{ $profile['note'] }}</p>
        </div>
        <div class="page-actions">
            <span class="badge">{{ $profile['short'] }}</span>
            <span class="badge {{ ($nodeData['status'] ?? '') === 'online' ? 'success' : 'danger' }}">
                {{ $vmStatus($nodeData['status'] ?? '') }}
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="note success" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <div class="grid-3" style="margin-bottom:16px">
        <div class="panel"><div class="panel-body">
            <div class="small muted">节点 ID</div><div class="mono">{{ $nodeData['id'] ?? '—' }}</div>
            <div class="small muted" style="margin-top:8px">区域</div><div>{{ $nodeData['region'] ?? '—' }}</div>
        </div></div>
        <div class="panel"><div class="panel-body">
            <div class="small muted">Controller 连接</div><div class="mono small">{{ $nodeData['url'] ?? '—' }}</div>
            <div class="small muted" style="margin-top:8px">Agent</div><div class="mono">{{ $nodeData['agent_version'] ?: '—' }}</div>
        </div></div>
        <div class="panel"><div class="panel-body">
            <div class="small muted">虚拟机</div><div>{{ $overview['vms_running'] ?? ($nodeData['vms_running'] ?? 0) }} 运行 / {{ $overview['vms_total'] ?? ($nodeData['vms_total'] ?? 0) }} 总计</div>
            <div class="small muted" style="margin-top:8px">IP 池</div><div>{{ count($pools) }} 个</div>
        </div></div>
    </div>

    @if ($profile['virtual_machine'])
        <div class="panel" style="margin-bottom:16px">
            <div class="panel-head"><div><h2>创建虚拟机</h2><div class="small muted">通过 Controller 创建，节点平台：{{ $profile['label'] }}</div></div></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.controller.create', $nodeData['id']) }}">
                    @csrf
                    <div class="grid-3">
                        <label class="field">名称<input class="input" name="name" value="{{ old('name', 'ecs-'.strtolower(\Illuminate\Support\Str::random(4))) }}" required></label>
                        <label class="field">CPU 核数<input class="input" type="number" name="cores" value="{{ old('cores', 2) }}" min="1" max="128" required></label>
                        <label class="field">内存 GB<input class="input" type="number" name="memory_gb" value="{{ old('memory_gb', 4) }}" min="1" required></label>
                        <label class="field">系统盘 GB<input class="input" type="number" name="disk_gb" value="{{ old('disk_gb', 40) }}" min="10" required></label>
                        <label class="field">数据盘 GB<input class="input" type="number" name="data_disk_gb" value="{{ old('data_disk_gb', 0) }}" min="0"></label>
                        <label class="field">系统类型
                            <select class="select" name="ostype">
                                <option value="win2k22">Windows Server 2022</option>
                                <option value="win2019">Windows Server 2019</option>
                                <option value="l26">Linux / 其他</option>
                            </select>
                        </label>
                        <label class="field">系统镜像
                            <select class="select" name="image_id">
                                <option value="">不使用镜像</option>
                                @foreach ($images as $image)
                                    <option value="{{ $image['id'] ?? '' }}">{{ $image['name'] ?? '' }} · {{ $image['ostype'] ?? '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">IP 池
                            <select class="select" name="ip_pool_id">
                                <option value="">不分配固定 IP</option>
                                @foreach ($pools as $pool)
                                    <option value="{{ $pool['id'] ?? '' }}">{{ $pool['name'] ?? '' }} · {{ $pool['cidr'] ?? '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">网络类型
                            <select class="select" name="network_mode">
                                <option value="private">共享 IP / NAT</option>
                                <option value="public">独立 IP</option>
                            </select>
                        </label>
                        <label class="field">初始密码<input class="input" name="password" placeholder="留空则不修改"></label>
                        <label class="field">创建后启动
                            <select class="select" name="start"><option value="1">是</option><option value="0">否</option></select>
                        </label>
                    </div>
                    <div style="margin-top:14px"><button class="btn btn-primary" type="submit">创建虚拟机</button></div>
                </form>
            </div>
        </div>
    @else
        <div class="note info" style="margin-bottom:16px">{{ $profile['note'] }} 裸金属不在 Controller 虚拟机接口中创建，请在节点详情中维护对应业务服务。</div>
    @endif

    <div class="panel" style="margin-bottom:16px">
        <div class="panel-head"><div><h2>虚拟机</h2><div class="small muted">操作会直接提交到 Controller。</div></div></div>
        <div class="panel-body tight">
            @if ($vmError)
                <div class="note danger" style="margin:12px">读取虚拟机失败：{{ $vmError }}</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>名称</th><th>ID</th><th>IP</th><th>规格</th><th>状态</th><th class="actions">操作</th></tr></thead>
                        <tbody>
                        @forelse ($vms as $vm)
                            @php($id = (string) ($vm['id'] ?? $vm['vmid'] ?? ''))
                            <tr>
                                <td><span class="strong">{{ $vm['name'] ?? $id }}</span></td>
                                <td class="mono small">{{ $id }}</td>
                                <td class="mono small">{{ $vm['ip'] ?? '—' }}</td>
                                <td class="small">{{ $vm['cores'] ?? 0 }}C / {{ $vm['memory_mb'] ?? 0 }}MB / {{ $vm['disk_gb'] ?? 0 }}GB</td>
                                <td><span class="badge {{ ($vm['status'] ?? '') === 'running' ? 'success' : 'warning' }}">{{ $vmStatus($vm['status'] ?? '') }}</span></td>
                                <td class="actions">
                                    @foreach (['start' => '启动', 'stop' => '关机', 'reboot' => '重启'] as $action => $label)
                                        <form method="POST" action="{{ route('admin.controller.action', [$nodeData['id'], $id]) }}" style="display:inline">
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $action }}">
                                            <button class="btn btn-ghost btn-sm" type="submit">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                    <form method="POST" action="{{ route('admin.controller.action', [$nodeData['id'], $id]) }}" style="display:inline" onsubmit="return confirm('确认删除该虚拟机？')">
                                        @csrf
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">该节点暂无虚拟机。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if (! $profile['virtual_machine'])
        <div class="panel" style="margin-bottom:16px">
            <div class="panel-head"><div><h2>登记裸金属服务</h2><div class="small muted">财务侧记录物理服务，节点与 Controller 保持关联。</div></div></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.controller.baremetal-service', $nodeData['id']) }}">
                    @csrf
                    <div class="grid-3">
                        <label class="field">客户
                            <select class="select" name="user_id" required>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->email }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">服务名称<input class="input" name="name" value="{{ old('name', '裸金属-'.strtoupper(\Illuminate\Support\Str::random(4))) }}" required></label>
                        <label class="field">IP 地址<input class="input" name="ip_address" value="{{ old('ip_address') }}" placeholder="203.0.113.10"></label>
                        <label class="field">CPU<input class="input" type="number" name="cpu" value="{{ old('cpu', 8) }}" min="1"></label>
                        <label class="field">内存 GB<input class="input" type="number" name="memory_gb" value="{{ old('memory_gb', 16) }}" min="1"></label>
                        <label class="field">磁盘 GB<input class="input" type="number" name="disk_gb" value="{{ old('disk_gb', 500) }}" min="1"></label>
                        <label class="field">到期时间<input class="input" type="date" name="expires_at" value="{{ old('expires_at') }}"></label>
                    </div>
                    <div style="margin-top:14px"><button class="btn btn-primary" type="submit">登记服务</button></div>
                </form>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><div><h2>关联业务服务</h2><div class="small muted">裸金属节点对应的财务服务记录。</div></div></div>
            <div class="panel-body tight">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>服务号</th><th>客户</th><th>状态</th><th>到期</th></tr></thead>
                        <tbody>
                        @forelse ($localServices as $service)
                            <tr>
                                <td class="mono small">{{ $service->service_no }}</td>
                                <td>{{ $service->user?->name ?? '—' }}</td>
                                <td><x-status :status="$service->status" /></td>
                                <td class="small">{{ $service->expires_at?->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-empty">暂无关联服务。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
