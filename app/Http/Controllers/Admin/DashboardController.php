<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudNode;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReconciliationRun;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $recentOrders = Order::query()
            ->with('user')
            ->latest()
            ->limit(6)
            ->get();

        $recentTickets = Ticket::query()
            ->with('user')
            ->latest()
            ->limit(6)
            ->get();

        $nodes = CloudNode::query()
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        $paidStatuses = ['paid', 'partially_refunded', 'refunded'];
        $monthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        $revenue = (float) Order::query()
            ->whereIn('status', $paidStatuses)
            ->selectRaw('COALESCE(SUM(total - refunded_amount), 0) as net')
            ->value('net');

        $monthRevenue = (float) Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('paid_at', '>=', $monthStart)
            ->selectRaw('COALESCE(SUM(total - refunded_amount), 0) as net')
            ->value('net');

        $lastMonthRevenue = (float) Order::query()
            ->whereIn('status', $paidStatuses)
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('COALESCE(SUM(total - refunded_amount), 0) as net')
            ->value('net');

        $revenueGrowth = $lastMonthRevenue > 0
            ? (($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100
            : ($monthRevenue > 0 ? 100.0 : 0.0);

        $refundedThisMonth = (float) Order::query()
            ->where('refunded_at', '>=', $monthStart)
            ->sum('refunded_amount');

        $trendStart = now()->subDays(13)->startOfDay();
        $trendRows = Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('paid_at', '>=', $trendStart)
            ->selectRaw('DATE(paid_at) as day, SUM(total - refunded_amount) as amount')
            ->groupBy('day')
            ->pluck('amount', 'day');

        $revenueTrend = collect(range(0, 13))->map(function (int $offset) use ($trendStart, $trendRows): array {
            $date = $trendStart->copy()->addDays($offset);
            $key = $date->format('Y-m-d');

            return [
                'date' => $date->format('m-d'),
                'amount' => (float) ($trendRows[$key] ?? 0),
            ];
        })->all();
        $trendMax = max(1, collect($revenueTrend)->max('amount'));

        $serviceStatuses = Service::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', $paidStatuses)
            ->selectRaw('order_items.product_name, SUM(order_items.amount) as amount, COUNT(*) as qty')
            ->groupBy('order_items.product_name')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        $notificationSent = NotificationLog::query()->where('status', 'sent')->count();
        $notificationFailed = NotificationLog::query()->where('status', 'failed')->count();
        $notificationAttempted = $notificationSent + $notificationFailed;

        return view('admin.dashboard', [
            'stats' => [
                'revenue' => $revenue,
                'month_revenue' => $monthRevenue,
                'last_month_revenue' => $lastMonthRevenue,
                'revenue_growth' => $revenueGrowth,
                'refunded_this_month' => $refundedThisMonth,
                'customers' => User::query()->count(),
                'active_services' => Service::query()->where('status', 'active')->count(),
                'suspended_services' => Service::query()->where('status', 'suspended')->count(),
                'pending_orders' => Order::query()->where('status', 'pending')->count(),
                'orders_this_month' => Order::query()->whereIn('status', $paidStatuses)->where('paid_at', '>=', $monthStart)->count(),
                'expiring_services' => Service::query()->whereIn('status', ['active', 'suspended'])->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
                'open_tickets' => Ticket::query()->whereIn('status', ['open', 'pending'])->count(),
                'nodes_online' => CloudNode::query()->where('status', 'online')->count(),
                'nodes_total' => CloudNode::query()->count(),
                'notification_rate' => $notificationAttempted > 0 ? round($notificationSent / $notificationAttempted * 100, 1) : 100,
                'notification_sent' => $notificationSent,
                'notification_failed' => $notificationFailed,
            ],
            'revenueTrend' => $revenueTrend,
            'trendMax' => $trendMax,
            'serviceStatuses' => $serviceStatuses,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
            'recentTickets' => $recentTickets,
            'nodes' => $nodes,
            'latestReconciliation' => ReconciliationRun::query()->latest('run_date')->first(),
        ]);
    }
}
