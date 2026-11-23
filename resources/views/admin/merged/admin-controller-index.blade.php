
    <div class="page-head">
        <div>
            <h1>主控对接</h1>
            <p>财务统一接入 Controller，查看宿主机性能并管理 KVM、Proxmox VE、Hyper-V 与裸金属节点。</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="#add-controller">添加主控</a>
        </div>
    </div>

    @if ($error)
        <div class="note danger" style="margin-bottom:14px">{{ $error }}</div>
    @endif
    @if (session('status'))
        <div class="note success" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <div class="grid-3" style="margin-bottom:16px">
        @forelse ($nodes as $node)
            @php
                $nodeId = (string) ($node['id'] ?? '');
                $platform = $profilesByNode[$nodeId] ?? ['label' => '未知', 'short' => '未知'];
                $overview = $overviews[$nodeId] ?? [];
                $host = is_array($overview['host'] ?? null) ? $overview['host'] : [];
                $cpu = max(0, min(100, (float) ($host['cpu_usage'] ?? 0)));
                $memory = max(0, min(100, (float) ($host['memory_usage'] ?? 0)));
                $disk = max(0, min(100, (float) ($host['disk_usage'] ?? 0)));
                $online = ($node['status'] ?? '') === 'online';
                $barClass = fn (float $value): string => $value >= 90 ? 'bar-red' : ($value >= 70 ? 'bar-amber' : 'bar-blue');
            @endphp
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>{{ $node['name'] ?? $nodeId }}</h2>
                        <div class="small muted">{{ $platform['short'] }} · {{ $node['region'] ?? '—' }} · <span class="mono">{{ $nodeId }}</span></div>
                    </div>
                    <span class="badge {{ $online ? 'success' : 'danger' }}">{{ $online ? '在线' : '离线' }}</span>
                </div>
                <div class="panel-body">
                    @if (! empty($overview['error']))
                        <div class="small" style="color:var(--dt-t-b91c1c,#b91c1c);margin-bottom:10px">性能读取失败：{{ $overview['error'] }}</div>
                    @endif
                    <div class="metric-line"><span>CPU</span><b>{{ number_format($cpu, 1) }}%</b></div>
                    <div class="metric-track"><i class="{{ $barClass($cpu) }}" style="width:{{ $cpu }}%"></i></div>
                    <div class="metric-line"><span>内存</span><b>{{ number_format($memory, 1) }}%</b></div>
                    <div class="metric-track"><i class="{{ $barClass($memory) }}" style="width:{{ $memory }}%"></i></div>
                    <div class="metric-line"><span>磁盘</span><b>{{ number_format($disk, 1) }}%</b></div>
                    <div class="metric-track"><i class="{{ $barClass($disk) }}" style="width:{{ $disk }}%"></i></div>
                    <div class="metric-line" style="margin-top:10px"><span>虚拟机</span><b>{{ $overview['vms_running'] ?? ($node['vms_running'] ?? 0) }} / {{ $overview['vms_total'] ?? ($node['vms_total'] ?? 0) }}</b></div>
                    <div class="metric-line"><span>CPU 型号</span><span class="small muted" style="max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $host['cpu_model'] ?? '—' }}</span></div>
                    <div class="metric-line"><span>Agent</span><span class="mono small">{{ $node['agent_version'] ?: '—' }}</span></div>
                    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.controller.show', $nodeId) }}">管理节点</a>
                        <span class="badge">{{ $platform['label'] }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="panel"><div class="panel-body"><div class="muted">Controller 暂未返回节点，请先添加主控。</div></div></div>
        @endforelse
    </div>

    <div class="panel" id="add-controller" style="margin-bottom:16px">
        <div class="panel-head"><div><h2>添加主控</h2><div class="small muted">填写 Controller 能访问到的 Agent / API 地址。PVE 使用 API 地址，Hyper-V 和 KVM 使用 Agent 地址。</div></div></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.controller.hosts.store') }}">
                @csrf
                <div class="grid-3">
                    <label class="field">节点 ID<input class="input" name="id" value="{{ old('id') }}" placeholder="kvm-114" required></label>
                    <label class="field">显示名称<input class="input" name="name" value="{{ old('name') }}" placeholder="苏州 7R13" required></label>
                    <label class="field">平台
                        <select class="select" name="provider" required>
                            <option value="kvm">KVM / 裸机虚拟化</option>
                            <option value="pve">Proxmox VE</option>
                            <option value="hyperv">Hyper-V</option>
                            <option value="baremetal">裸金属</option>
                        </select>
                    </label>
                    <label class="field">区域<input class="input" name="region" value="{{ old('region') }}" placeholder="江苏"></label>
                    <label class="field">API / Agent 地址<input class="input" name="url" value="{{ old('url') }}" placeholder="http://114.28.145.48:8443" required></label>
                    <label class="field">API / Agent Token<input class="input" name="token" value="{{ old('token') }}" autocomplete="new-password"></label>
                    <label class="field">节点公网 IP<input class="input" name="node_ip" value="{{ old('node_ip') }}" placeholder="114.28.145.48"></label>
                    <label class="field">公网桥 / 交换机<input class="input" name="public_bridge" value="{{ old('public_bridge', 'virbr0') }}"></label>
                    <label class="field">内网桥 / 交换机<input class="input" name="private_bridge" value="{{ old('private_bridge', 'virbr0') }}"></label>
                    <label class="field">端口起始<input class="input" type="number" name="port_min" value="{{ old('port_min', 30000) }}" min="1" max="65535"></label>
                    <label class="field">端口结束<input class="input" type="number" name="port_max" value="{{ old('port_max', 30020) }}" min="1" max="65535"></label>
                    <label class="field">DNS1<input class="input" name="dns1" value="{{ old('dns1', '223.5.5.5') }}" placeholder="223.5.5.5"></label>
                    <label class="field">DNS2<input class="input" name="dns2" value="{{ old('dns2', '223.6.6.6') }}" placeholder="223.6.6.6"></label>
                    <label class="field">排除端口<input class="input" name="exclude_ports" value="{{ old('exclude_ports') }}" placeholder="多个端口用英文逗号分隔"></label>
                </div>
                <div style="margin-top:14px"><button class="btn btn-primary" type="submit">添加主控</button></div>
            </form>
        </div>
    </div>

    <style>
        .metric-line{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12px;color:var(--dt-t-64748b,#64748b);margin-top:9px}
        .metric-line b{color:var(--dt-t-0f172a,#0f172a);font-size:13px}
        .metric-track{height:8px;border-radius:99px;background:var(--dt-b-e8eef7,#e8eef7);overflow:hidden;margin-top:5px}
        .metric-track i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--dt-b-38bdf8,#38bdf8),var(--dt-b-2563eb,#2563eb))}
        .metric-track i.bar-amber{background:linear-gradient(90deg,var(--dt-b-fbbf24,#fbbf24),var(--dt-b-f59e0b,#f59e0b))}
        .metric-track i.bar-red{background:linear-gradient(90deg,var(--dt-b-f87171,#f87171),var(--dt-b-dc2626,#dc2626))}
    </style>

