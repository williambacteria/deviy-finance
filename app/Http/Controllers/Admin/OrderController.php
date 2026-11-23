<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,paid,processing,cancelled,refunded,partially_refunded'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', \Illuminate\Validation\Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'date_field' => ['nullable', 'in:created_at,paid_at'],
            'q' => ['nullable', 'string', 'max:190'],
        ]);
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['q'] ?? ''));
        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';
        $dateField = $filters['date_field'] ?? 'created_at';
        $servicePending = fn ($query) => $query->whereHas('service', fn ($service) => $service->whereNotIn('status', ['terminated', 'cancelled'])->where(fn ($state) => $state->whereIn('status', ['pending', 'provisioning', 'failed'])->orWhereIn('provision_status', ['queued', 'dispatched', 'failed', 'upstream_provisioning', 'network_pending'])));
        $supplierPending = fn ($query) => $query->whereNotIn('status', ['active', 'suspended', 'terminated', 'cancelled']);
        $orders = Order::query()->with('user')->withCount('items')
            ->withExists(['items as has_pending_resource' => $servicePending, 'upstreamOrders as has_pending_supplier' => $supplierPending])
            ->when($status === 'processing', fn ($query) => $query->where('status', 'paid')->where(fn ($nested) => $nested->whereHas('items', $servicePending)->orWhereHas('upstreamOrders', $supplierPending)))
            ->when($status !== '' && $status !== 'processing', fn ($query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn ($query) => $query->where($dateField, '>=', \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo !== '', fn ($query) => $query->where($dateField, '<', \Illuminate\Support\Carbon::parse($dateTo)->addDay()->startOfDay()))
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('order_no', 'like', "%{$search}%")->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.orders.index', compact('orders', 'status', 'search', 'dateFrom', 'dateTo', 'dateField'));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.product', 'invoices', 'payments']);

        return view('admin.orders.show', compact('order'));
    }

    public function refund(Request $request, Order $order, PaymentService $payments, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $payments->refund($order, (string) $data['amount'], $data['reason']);
        $audit->record($request->user('admin'), 'order.refunded', '订单退款 '.$data['amount'].'：'.$data['reason'], $order, [
            'amount' => $data['amount'],
        ]);

        return back()->with('status', '退款已退回客户余额。');
    }
}
