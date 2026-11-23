
    <div class="page-head">
        <div><h1>实名审核</h1><p>统一处理个人三要素认证和企业实名资料。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.customers.index') }}">返回客户列表</a>
    </div>

    @if (session('status'))
        <div class="note success" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    @php
        $statusLabels = ['unverified' => '未认证', 'pending' => '待审核', 'verified' => '已认证', 'rejected' => '已驳回'];
        $statusClasses = ['unverified' => 'disabled', 'pending' => 'pending', 'verified' => 'active', 'rejected' => 'failed'];
    @endphp

    <div class="panel">
        <div class="product-list-toolbar">
            <div class="product-list-title">
                <h2>实名记录</h2>
                <span>共 {{ $users->total() }} 条</span>
            </div>
            <form method="GET" class="product-filter-form" action="{{ url()->current() }}">
<input type="hidden" name="tab" value="{{ $workspaceTab ?? '' }}"><input type="hidden" name="section" value="{{ $section ?? '' }}">
                <input class="input" type="search" name="q" value="{{ $search }}" placeholder="客户 / 公司 / 信用代码">
                <select class="select" name="status">
                    <option value="">全部状态</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}（{{ $counts[$value] ?? 0 }}）</option>
                    @endforeach
                </select>
                <select class="select" name="type">
                    <option value="">全部类型</option>
                    <option value="personal" @selected($type === 'personal')>个人认证</option>
                    <option value="enterprise" @selected($type === 'enterprise')>企业认证</option>
                </select>
                <button class="btn btn-primary" type="submit">筛选</button>
                @if ($search !== '' || $status !== '' || $type !== '')
                    <a class="btn btn-ghost" href="{{ route('admin.real-names.index') }}">重置</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>客户</th><th>认证类型 / 主体</th><th>证件 / 信用代码</th><th>手机号</th><th>提交时间</th><th>状态</th><th>资料</th><th class="actions">审核</th></tr></thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <a class="strong" href="{{ route('admin.customers.show', $user) }}">{{ $user->name }}</a>
                            <div class="sub">{{ $user->email }}</div>
                        </td>
                        <td class="small">
                            @if ($user->real_name_type === 'enterprise')
                                <span class="chip">企业</span>
                                <div class="sub">{{ $user->real_name_company ?: '未填写公司' }}</div>
                                <div class="sub">法人：{{ $user->real_name_legal_name ?: '未填写' }}</div>
                            @else
                                <span class="chip">个人</span>
                                <div class="sub">{{ $user->real_name ?: '未填写姓名' }}</div>
                            @endif
                        </td>
                        <td class="small mono">
                            @if ($user->real_name_type === 'enterprise')
                                {{ $user->real_name_credit_code ?: '—' }}
                            @else
                                {{ $user->real_name_id ? $user->maskedRealNameId() : '—' }}
                            @endif
                        </td>
                        <td class="small mono">
                            {{ $user->real_name_type === 'enterprise' ? ($user->real_name_legal_phone ?: '—') : ($user->real_name_phone ?: '—') }}
                        </td>
                        <td class="small">{{ $user->real_name_submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td><x-status :status="$statusClasses[$user->real_name_status] ?? 'muted'" :label="$statusLabels[$user->real_name_status] ?? $user->real_name_status" /></td>
                        <td class="small">
                            @if ($user->real_name_type === 'enterprise' && is_array($user->real_name_license))
                                <a href="{{ route('admin.customers.real-name.file', [$user, 'license']) }}" target="_blank">营业执照</a>
                                @if (is_array($user->real_name_legal_id))
                                    <span class="muted"> / </span><a href="{{ route('admin.customers.real-name.file', [$user, 'legal_id']) }}" target="_blank">法人证件</a>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.real-names.edit', $user) }}">编辑</a>
                            @if ($user->real_name_status === 'pending')
                                <form method="POST" action="{{ route('admin.customers.real-name.verify', $user) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-primary btn-sm" type="submit">通过</button>
                                </form>
                                <form method="POST" action="{{ route('admin.customers.real-name.reject', $user) }}" class="inline-form" style="display:inline-flex;gap:5px;margin-top:5px">
                                    @csrf
                                    <input class="input" name="reason" placeholder="驳回原因" required style="min-width:130px">
                                    <button class="btn btn-danger btn-sm" type="submit">驳回</button>
                                </form>
                            @elseif ($user->real_name_status === 'rejected')
                                <span class="small muted">{{ $user->real_name_reject_reason ?: '已驳回' }}</span>
                            @else
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.customers.show', $user) }}">查看客户</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="table-empty">没有符合条件的实名记录。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="panel-footer">{{ $users->links('partials.pagination') }}</div>
        @endif
    </div>

