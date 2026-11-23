<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\AlipayGateway;
use App\Services\Payments\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AlipayNotifyController extends Controller
{
    public function redirectToCashier(string $paymentNo, AlipayGateway $alipay): RedirectResponse
    {
        $payment = Payment::query()->where('payment_no', $paymentNo)->firstOrFail();
        abort_unless($payment->status === 'pending', 404);

        return redirect()->away($alipay->paymentUrl($payment, ($payment->order_id !== null ? '订单支付 ' : '账户充值 ').$payment->payment_no));
    }

    public function return(Request $request): RedirectResponse
    {
        $payment = Payment::where('payment_no',(string)$request->input('out_trade_no',''))->first();
        if ($payment?->order_id && auth()->id() === $payment->user_id) { return redirect()->route('console.orders.show',$payment->order_id)->with('status','已返回订单页，付款结果以服务端查询为准。'); }
        return redirect()
            ->route('console.recharge.index')
            ->with('status', '已从支付宝返回，到账结果请以下方支付状态为准。');
    }

    public function notify(Request $request, AlipayGateway $alipay, PaymentService $payments): Response
    {
        if (! $alipay->verify($request->all())) {
            Log::warning('Alipay callback signature rejected', ['payment_no' => $request->input('out_trade_no')]);

            return response('failure', 400);
        }

        if (! in_array($request->input('trade_status'), ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            return response('success');
        }

        $payment = Payment::query()->where('payment_no', $request->input('out_trade_no'))->first();
        if (! $payment) {
            return response('failure', 404);
        }

        if ($payment->gateway !== 'alipay' || ! is_numeric($request->input('total_amount')) || Money::compare((string) $payment->amount, (string) $request->input('total_amount')) !== 0 || (string) $request->input('trade_no', '') === '') {
            return response('failure', 422);
        }

        if ($payment->status === 'succeeded') {
            return response('success');
        }

        if (! in_array($payment->status,['pending','cancelled'],true) || Money::compare((string) $payment->amount, (string) $request->input('total_amount')) !== 0) {
            return response('failure', 422);
        }

        $payments->completePayment($payment, (string) $request->input('trade_no'));

        return response('success');
    }
}
