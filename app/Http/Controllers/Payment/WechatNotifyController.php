<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WechatPayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WechatNotifyController extends Controller
{
    public function notify(Request $request, WechatPayGateway $wechat, PaymentService $payments): JsonResponse
    {
        try {
            $callback = $wechat->verifyCallback($request);
        } catch (Throwable $exception) {
            Log::warning('Wechat callback rejected', ['error' => $exception->getMessage()]);

            return response()->json(['code' => 'FAIL', 'message' => $exception->getMessage()], 401);
        }

        $resource = $callback['resource'];
        if (($callback['event_type'] ?? '') !== 'TRANSACTION.SUCCESS' || ($resource['trade_state'] ?? '') !== 'SUCCESS') {
            return response()->json(['code' => 'SUCCESS', 'message' => '成功']);
        }

        $payment = Payment::query()->where('payment_no', $resource['out_trade_no'] ?? '')->first();
        if (! $payment) {
            return response()->json(['code' => 'FAIL', 'message' => '支付单不存在'], 404);
        }


        try {
            $wechat->validateTransaction($payment, $resource);
        } catch (Throwable $exception) {
            Log::warning('Wechat transaction rejected', ['payment_no' => $payment->payment_no]);

            return response()->json(['code' => 'FAIL', 'message' => '支付信息不匹配'], 422);
        }
        if ($payment->status === 'succeeded') {
            return response()->json(['code' => 'SUCCESS', 'message' => '成功']);
        }
        if (! in_array($payment->status,['pending','cancelled'],true)) {
            return response()->json(['code' => 'FAIL', 'message' => '支付单状态不可处理'], 422);
        }

        $payments->completePayment($payment, (string) ($resource['transaction_id'] ?? ''));

        return response()->json(['code' => 'SUCCESS', 'message' => '成功']);
    }
}
