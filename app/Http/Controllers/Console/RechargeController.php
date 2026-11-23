<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\AlipayGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WechatPayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RechargeController extends Controller
{
    public function index(Request $request, PaymentService $paymentService, AlipayGateway $alipay, WechatPayGateway $wechat): View
    {
        $payments = $request->user()->payments()
            ->whereNull('order_id')
            ->latest()
            ->limit(10)
            ->get();

        foreach ($payments->where('gateway', 'alipay')->where('status', 'pending')->take(3) as $payment) {
            $paymentService->syncAlipayRecharge($payment, $alipay);
        }
        foreach ($payments->where('gateway', 'wechat')->where('status', 'pending')->take(3) as $payment) {
            $paymentService->syncWechatRecharge($payment, $wechat);
        }
        $request->user()->refresh();

        $selectedPayment = null;
        if ($request->filled('payment')) {
            $request->validate(['payment' => ['required', 'integer', 'min:1']]);
            $selectedPayment = Payment::where('user_id',$request->user()->id)->whereNull('order_id')->findOrFail($request->integer('payment'));
            $selectedPayment = $selectedPayment->gateway === 'wechat' ? $paymentService->syncWechatRecharge($selectedPayment,$wechat) : $paymentService->syncAlipayRecharge($selectedPayment,$alipay);
        }
        $pendingPayment = null;
        if ($request->user()) {
            $paymentId = (int) session('pending_payment_id');
            if ($paymentId > 0) {
                $pendingPayment = Payment::query()
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'pending')
                    ->find($paymentId);
            }
        }

        if ($selectedPayment) { $pendingPayment = $selectedPayment->status === 'pending' ? $selectedPayment : null; }
        $qrCurrent = $pendingPayment && app(\App\Services\Payments\RechargeResume::class)->qrIsCurrent($pendingPayment);
        return view('console.recharge.index', [
            'selectedPayment' => $selectedPayment,
            'qrCurrent' => $qrCurrent,
            'payments' => $payments,
            'pendingPayment' => $pendingPayment,
            'alipayEnabled' => app(AlipayGateway::class)->isConfigured(),
            'wechatEnabled' => app(WechatPayGateway::class)->isConfigured(),
            'alipayQr' => $qrCurrent ? (string) data_get($pendingPayment?->payload, 'qr_code', '') : '',
            'pendingPaymentId' => session('pending_payment_id'),
        ]);
    }

    public function store(Request $request, PaymentService $payments, AlipayGateway $alipay, WechatPayGateway $wechat): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:50000'],
            'gateway' => ['required', 'in:alipay,wechat,alipay_sim,wechat_sim,manual'],
        ]);

        if ($data['gateway'] === 'alipay' && ! $alipay->isConfigured()) {
            throw ValidationException::withMessages(['gateway' => '支付宝支付尚未配置。']);
        }

        if ($data['gateway'] === 'wechat' && ! $wechat->isConfigured()) {
            throw ValidationException::withMessages(['gateway' => '微信支付尚未配置。']);
        }

        $payment = $payments->createRecharge($request->user(), $data['gateway'], $data['amount']);

        if ($data['gateway'] === 'alipay') {
            try {
                $qr = $alipay->createQrPayment($payment, '账户充值 '.$payment->payment_no);
            } catch (\Throwable $exception) {
                report($exception);
                $payment->update(['status' => 'failed']);

                return back()->withErrors(['gateway' => $exception->getMessage()]);
            }

            $payload = array_merge((array) $payment->payload, [
                'source' => 'recharge',
                'qr_code' => $qr['qr_code'],
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ]);
            if (($qr['fallback'] ?? false) === true) {
                $payload['page_pay_url'] = $qr['qr_code'];
                $payload['qr_code'] = route('payments.alipay.qr', $payment->payment_no);
            }

            $payment->update(['payload' => $payload]);

            return redirect()
                ->route('console.recharge.index')
                ->with('pending_payment_id', $payment->id)
                ->with('status', '支付宝二维码已生成，请使用支付宝扫码完成支付。');
        }


        if ($data['gateway'] === 'wechat') {
            try {
                $native = $wechat->createNative($payment, '账户充值 '.$payment->payment_no);
                $payment->update(['payload' => array_merge((array) $payment->payload, ['qr_code' => (string) $native['code_url'], 'expires_at' => now()->addMinutes(15)->toIso8601String()])]);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['gateway' => '微信支付二维码暂未取得，请稍后查看充值记录或重试。'])->with('pending_payment_id', $payment->id);
            }

            return redirect()->route('console.recharge.index')->with('pending_payment_id', $payment->id)->with('status', '微信支付二维码已生成，请使用微信扫码完成支付。');
        }

        return redirect()
            ->route('console.recharge.index')
            ->with('pending_payment_id', $payment->id)
            ->with('status', '充值单已创建，请完成支付。');
    }


    public function resume(Request $request, Payment $payment, \App\Services\Payments\RechargeResume $resume): RedirectResponse
    {
        abort_unless((int) $payment->user_id === (int) $request->user()->id && $payment->order_id === null, 404);
        try {
            $active = $resume->resume($request->user(), $payment);
        } catch (\RuntimeException $exception) {
            report($exception);
            return redirect()->route('console.recharge.index', ['payment' => $payment->id])->withErrors(['payment' => '暂时无法恢复付款，请稍后重试；若已扣款请勿重复支付，可联系客服核对。']);
        }

        return redirect()->route('console.recharge.index', ['payment' => $active->id])->with('pending_payment_id', $active->id)->with('status', $active->status === 'succeeded' ? '该充值单已支付，余额已到账。' : ($active->id === $payment->id ? '支付已恢复，请扫码完成付款。' : '原订单已关闭，已关联新的支付记录，请点击继续支付或扫描二维码。'));
    }

    public function status(Request $request, Payment $payment, PaymentService $paymentService, AlipayGateway $alipay, WechatPayGateway $wechat): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        $payment = $payment->gateway === 'wechat' ? $paymentService->syncWechatRecharge($payment, $wechat) : $paymentService->syncAlipayRecharge($payment, $alipay);
        $request->user()->refresh();

        return response()->json([
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
            'balance' => $request->user()->balance,
        ]);
    }

    public function simulate(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        $payments->completeRecharge($payment);

        return redirect()->route('console.recharge.index')->with('status', '充值成功，余额已到账。');
    }
}
