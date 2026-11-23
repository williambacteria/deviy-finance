
    <div class="page-head">
        <div><h1>客户管理</h1><p>客户资料、余额、服务与工单的统一视图。</p></div>
        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('admin.customers.create') }}">+ 新建客户</a>
            <form method="GET" class="filter-bar" action="{{ url()->current() }}">
<input type="hidden" name="tab" value="{{ $workspaceTab ?? '' }}"><input type="hidden" name="section" value="{{ $section ?? '' }}">
                <input class="input" type="search" name="q" value="{{ $search }}" placeholder="姓名 / 邮箱 / 手机 / QQ">
                <select class="select" name="status">
                    <option value="">全部状态</option>
                    <option value="online" @selected($status === 'online')>在线</option>
                    <option value="offline" @selected($status === 'offline')>离线</option>
                    <option value="disabled" @selected($status === 'disabled')>停用</option>
                </select>
                <button class="btn btn-ghost" type="submit">筛选</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>客户</th><th>分组</th><th>余额</th><th>服务</th><th>订单</th><th>状态</th><th>注册时间</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            @php
                                $presence = $customer->status !== 'active'
                                    ? 'disabled'
                                    : (($customer->last_seen_at && $customer->last_seen_at->gte(now()->subMinutes(5))) ? 'online' : 'offline');
                                $presenceLabel = ['online' => '在线', 'offline' => '离线', 'disabled' => '停用'][$presence];
                            @endphp
                            <tr>
                                <td><a class="strong" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a><div class="sub">{{ $customer->email }} · QQ {{ $customer->qq ?: '未填写' }} · {{ $customer->company ?: '个人' }}</div></td>
                                <td><span class="chip">{{ $customer->customer_group === 'vip' ? 'VIP' : '标准' }}</span></td>
                                <td class="strong">{{ \App\Support\Money::format($customer->balance) }}</td>
                                <td>{{ $customer->services_count }}</td>
                                <td>{{ $customer->orders_count }}</td>
                                <td><x-status :status="$presence" :label="$presenceLabel" /></td>
                                <td class="small muted">{{ $customer->created_at?->format('Y-m-d') }}</td>
                                <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('admin.customers.show', $customer) }}">详情</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">没有匹配的客户。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $customers->links('partials.pagination') }}</div>
        </div>
    </div>

