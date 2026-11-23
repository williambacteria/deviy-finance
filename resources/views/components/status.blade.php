@props(['status', 'label' => null])

@php
    $labels = [
        'active' => '运行中',
        'pending' => '待处理',
        'confirming' => '等待交付确认',
        'needs_review' => '需人工核对',
        'awaiting_payment' => '等待付款',
        'provisioning' => '开通中',
        'suspended' => '已暂停',
        'stopped' => '已关机',
        'terminated' => '已终止',
        'paid' => '已支付',
        'unpaid' => '待支付',
        'partial' => '部分支付',
        'overdue' => '已逾期',
        'partially_refunded' => '部分退款',
        'refunded' => '已退款',
        'succeeded' => '成功',
        'sent' => '已发送',
        'success' => '已开通',
        'failed' => '失败',
        'queued' => '排队中',
        'open' => '待回复',
        'resolved' => '已解决',
        'closed' => '已关闭',
        'cancelled' => '已关闭',
        'online' => '在线',
        'offline' => '离线',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$status]) }}>{{ $label ?? ($labels[$status] ?? $status) }}</span>
