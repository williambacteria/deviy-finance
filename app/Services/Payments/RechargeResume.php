<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RechargeResume
{
    public function __construct(private PaymentService $payments, private WechatPayGateway $wechat, private AlipayGateway $alipay) {}

    public function resume(User $user, Payment $payment): Payment
    {
        abort_unless((int) $payment->user_id === (int) $user->id && $payment->order_id === null, 404);
        abort_unless(in_array($payment->gateway, ['wechat', 'alipay'], true), 422, '该充值方式不支持扫码续付。');
        $lock = fopen(storage_path('app/private/recharge-resume-'.$payment->id.'.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) { fclose($lock); }
            throw new RuntimeException('正在恢复这笔充值，请稍后重试。');
        }

        try {
            $payment->refresh();
            $replacement = data_get($payment->payload, 'replacement_payment_id');
            if ($replacement) {
                $child = Payment::where('user_id', $user->id)->whereNull('order_id')->findOrFail($replacement);
                abort_unless($child->gateway === $payment->gateway && bccomp((string) $child->amount, (string) $payment->amount, 6) === 0 && (int) data_get($child->payload, 'retry_of') === (int) $payment->id, 422, '关联支付记录异常，请联系客服。');
                abort_unless((int) $child->id > (int) $payment->id, 422, '关联支付记录异常，请联系客服。');
                return $this->resume($user, $child);
            }
            if ($payment->status === 'succeeded') { return $payment; }
            abort_unless(in_array($payment->status, ['pending', 'failed', 'cancelled'], true), 422, '当前状态无法继续支付。');

            if ($payment->gateway === 'wechat') {
                abort_unless($this->wechat->isConfigured(), 422, '微信支付暂不可用。');
                $remote = $this->wechat->query($payment->payment_no);
                $state = (string) ($remote['trade_state'] ?? '');
                if ($state === 'SUCCESS') {
                    $this->wechat->validateTransaction($payment, $remote);
                    return $this->settle($payment, (string) $remote['transaction_id']);
                }
                if (in_array($state, ['USERPAYING', 'ACCEPT'], true)) {
                    throw new RuntimeException('微信正在处理付款，请稍后查看到账状态，不要重复支付。');
                }
                if ($state === 'NOTPAY' && $payment->status === 'pending' && $this->qrIsCurrent($payment)) {
                    return $payment;
                }
                if ($state === 'NOTPAY') {
                    $this->wechat->closeUnpaid($payment->payment_no);
                    $remote = $this->wechat->query($payment->payment_no);
                    $state = (string) ($remote['trade_state'] ?? '');
                    if ($state === 'SUCCESS') {
                        $this->wechat->validateTransaction($payment, $remote);
                        return $this->settle($payment, (string) $remote['transaction_id']);
                    }
                    if ($state !== 'CLOSED') {
                        throw new RuntimeException('原订单关闭尚未确认，请稍后重新打开记录。');
                    }
                }
                if (in_array($state, ['CLOSED', 'REVOKED', 'PAYERROR'], true)) {
                    $payment = $this->replace($payment);
                } elseif ($state === 'NOT_FOUND') {
                    $payment = $payment->status === 'pending' ? $payment : $this->replace($payment);
                } else {
                    throw new RuntimeException('微信订单状态暂无法确认，请稍后重试。');
                }
                $native = $this->wechat->createNative($payment, '账户充值 '.$payment->payment_no);
                return $this->saveQr($payment, (string) $native['code_url']);
            }

            abort_unless($this->alipay->isConfigured(), 422, '支付宝支付暂不可用。');
            $remote = $this->alipay->query($payment->payment_no);
            $state = (string) ($remote['trade_status'] ?? '');
            if (in_array($state, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
                abort_unless((string) ($remote['out_trade_no'] ?? '') === $payment->payment_no && bccomp((string) ($remote['total_amount'] ?? '-1'), (string) $payment->amount, 2) === 0 && (string) ($remote['trade_no'] ?? '') !== '', 422, '支付宝订单信息不匹配。');
                return $this->settle($payment, (string) $remote['trade_no']);
            }
            if ($state === 'TRADE_CLOSED') { $payment = $this->replace($payment); }
            elseif ($state !== 'WAIT_BUYER_PAY' && ($remote['sub_code'] ?? '') !== 'ACQ.TRADE_NOT_EXIST') {
                throw new RuntimeException('支付宝订单状态暂无法确认，请稍后重试。');
            } elseif ($payment->status === 'pending' && $this->qrIsCurrent($payment)) { return $payment; }
            elseif ($payment->status !== 'pending') {
                throw new RuntimeException('原支付宝订单仍可能付款，请联系客服核对后再支付。');
            }
            $qr = $this->alipay->createQrPayment($payment, '账户充值 '.$payment->payment_no);
            if (($qr['fallback'] ?? false) === true) {
                $payload = (array) $payment->payload;
                $payload['page_pay_url'] = $qr['qr_code'];
                $payment->update(['payload' => $payload]);
                $qr['qr_code'] = route('payments.alipay.qr', $payment->payment_no);
            }

            return $this->saveQr($payment, (string) $qr['qr_code']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function qrIsCurrent(Payment $payment): bool
    {
        if ((string) data_get($payment->payload, 'qr_code', '') === '') { return false; }
        try {
            $expires = data_get($payment->payload, 'expires_at');
            return $expires ? Carbon::parse($expires)->isFuture() : $payment->created_at->greaterThan(now()->subMinutes(15));
        } catch (\Throwable) { return false; }
    }

    private function saveQr(Payment $payment, string $qr): Payment
    {
        DB::transaction(function () use ($payment, $qr): void {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') { return; }
            $locked->update(['payload' => array_merge((array) $locked->payload, ['qr_code' => $qr, 'expires_at' => now()->addMinutes(15)->toIso8601String()])]);
        });

        return $payment->refresh();
    }

    private function settle(Payment $payment, string $transaction): Payment
    {
        return DB::transaction(function () use ($payment, $transaction): Payment {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'failed') { $locked->update(['status' => 'pending']); }
            return $this->payments->completeRecharge($locked, $transaction);
        });
    }

    private function replace(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'succeeded') { throw new RuntimeException('原订单已付款，请刷新充值记录。'); }
            if (data_get($locked->payload, 'replacement_payment_id')) {
                return Payment::where('user_id', $locked->user_id)->findOrFail(data_get($locked->payload, 'replacement_payment_id'));
            }
            $next = $this->payments->createRecharge($locked->user, $locked->gateway, (string) $locked->amount);
            $next->update(['payload' => array_merge((array) $next->payload, ['retry_of' => $locked->id, 'retry_of_no' => $locked->payment_no])]);
            $locked->update(['status' => 'cancelled', 'payload' => array_merge((array) $locked->payload, ['closed_confirmed_at' => now()->toIso8601String(), 'replacement_payment_id' => $next->id, 'replacement_payment_no' => $next->payment_no])]);

            return $next;
        });
    }
}
