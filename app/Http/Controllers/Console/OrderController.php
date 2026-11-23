<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()
            ->withCount('items')
            ->latest()
            ->paginate(12);

        return view('console.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOrder($request, $order);

        $order->load(['items.product', 'invoices', 'payments']);

        $cashierService=app(\App\Services\Payments\OrderCashier::class);
        $cashierService->reconcile($order);$order->refresh();
        $cashier=$cashierService->state($order);
        $alipayEnabled=app(\App\Services\Payments\AlipayGateway::class)->isConfigured();
        $wechatEnabled=app(\App\Services\Payments\WechatPayGateway::class)->isConfigured();
        return view('console.orders.show', compact('order','cashier','alipayEnabled','wechatEnabled'));
    }


    public function pay(Request $request, Order $order, \App\Services\Payments\OrderPaymentService $payments): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $payments->balance($request->user(), $order);
        return redirect()->route('console.orders.show', $order)->with('status', '支付成功，订单已进入交付流程。');
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
