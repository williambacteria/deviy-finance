@php
    $adminMenuUser = auth('admin')->user();
    $workspaceArea = request()->route('area');
    $unreadAdminNotices = $adminMenuUser?->adminNotices()->whereNull('read_at')->count() ?? 0;
@endphp
<div class="side-label">工作台</div>
<nav class="side-nav" aria-label="工作台">
    <a @class(['side-link', 'active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}"><x-icon name="dashboard" /> 概览</a>
    <a @class(['side-link', 'active' => $workspaceArea === 'messages' || request()->routeIs('admin.inbox.*', 'admin.ops.*', 'admin.oncall.*')]) href="{{ route('admin.workspace', ['area' => 'messages']) }}"><x-icon name="bell" /> 消息与告警 @if ($unreadAdminNotices > 0)<span class="badge failed" style="margin-left:auto">{{ $unreadAdminNotices }}</span>@endif</a>
</nav>
<div class="side-label">业务管理</div>
<nav class="side-nav" aria-label="业务管理">
    <a @class(['side-link', 'active' => $workspaceArea === 'customers' || request()->routeIs('admin.customers.*', 'admin.real-names.*')]) href="{{ route('admin.workspace', ['area' => 'customers']) }}"><x-icon name="users" /> 客户中心</a>
    <a @class(['side-link', 'active' => request()->routeIs('admin.services.*')]) href="{{ route('admin.services.index') }}"><x-icon name="server" /> 业务管理</a>
    @if ($adminMenuUser->isSuperAdmin())<a @class(['side-link', 'active' => $workspaceArea === 'catalog' || request()->routeIs('admin.products.*', 'admin.product-groups.*', 'admin.mofang.*', 'admin.pterodactyl.*')]) href="{{ route('admin.workspace', ['area' => 'catalog']) }}"><x-icon name="package" /> 商品中心</a>@endif
    <a @class(['side-link', 'active' => $workspaceArea === 'finance' || request()->routeIs('admin.orders.*', 'admin.reports.*')]) href="{{ route('admin.workspace', ['area' => 'finance']) }}"><x-icon name="wallet" /> 订单财务</a>
    <a @class(['side-link', 'active' => request()->routeIs('admin.tickets.*')]) href="{{ route('admin.tickets.index') }}"><x-icon name="ticket" /> 工单管理</a>
</nav>
<div class="side-label">平台管理</div>
<nav class="side-nav" aria-label="平台管理">
    @if (in_array($adminMenuUser->role, ['super_admin', 'support'], true))<a @class(['side-link', 'active' => $workspaceArea === 'website' || request()->routeIs('admin.announcements.*', 'admin.help-articles.*', 'admin.homepage-featured.*', 'admin.homepage-testimonials.*')]) href="{{ route('admin.workspace', ['area' => 'website']) }}"><x-icon name="globe" /> 网站内容</a>@endif
    @if ($adminMenuUser->isSuperAdmin())
        <a @class(['side-link', 'active' => request()->routeIs('admin.settings.*', 'admin.payments.*', 'admin.mail.*', 'admin.notifications.*', 'admin.plugins.*', 'admin.storage.*', 'admin.quarantine.*', 'admin.controller.*', 'admin.security.*', 'admin.audit.*')]) href="{{ route('admin.settings.index') }}"><x-icon name="settings" /> 系统设置</a>
    @else
        <a @class(['side-link', 'active' => request()->routeIs('admin.security.*')]) href="{{ route('admin.security.show') }}"><x-icon name="lock" /> 账户安全</a>
        <a @class(['side-link', 'active' => request()->routeIs('admin.audit.*')]) href="{{ route('admin.audit.index') }}"><x-icon name="activity" /> 操作日志</a>
    @endif
</nav>
