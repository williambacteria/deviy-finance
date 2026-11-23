<?php

namespace App\Services\Payments;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function createRecharge(User $user, string $gateway, string $amount): Payment
    {
        $this->assertGateway($gateway);
        $this->assertAmount($amount);

        return Payment::create([
            'payment_no' => DocumentNumber::make('PAY'),
            'user_id' => $user->id,
            'gateway' => $gateway,
            'amount' => $amount,
            'status' => 'pending',
            'payload' => ['source' => 'recharge'],
        ]);
    }

    /** Reconcile a recharge using an authenticated Alipay query; never trust browser return parameters. */
    public function syncAlipayRecharge(Payment $payment, AlipayGateway $alipay, bool $throwOnFailure = false): Payment
    {
        $payment->refresh();
        if ($payment->gateway !== 'alipay' || $payment->status !== 'pending' || $payment->order_id !== null || ! $alipay->isConfigured()) {
            return $payment;
        }
        if (! Cache::add('payments:alipay:query:'.$payment->id, true, 10)) {
            return $payment;
        }

        try {
            $result = $alipay->query($payment->payment_no);
            if ((string) ($result['code'] ?? '') !== '10000' || ! in_array($result['trade_status'] ?? '', ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
                return $payment;
            }
            $amount = (string) ($result['total_amount'] ?? '');
            $transactionId = (string) ($result['trade_no'] ?? '');
            if ((string) ($result['out_trade_no'] ?? '') !== $payment->payment_no || $transactionId === '' || ! is_numeric($amount) || Money::compare((string) $payment->amount, $amount) !== 0) {
                throw new RuntimeException('支付宝交易订单号、金额或交易号与本地充值单不匹配。');
            }

            return $this->completeRecharge($payment, $transactionId);
        } catch (\Throwable $exception) {
            Log::warning('Alipay recharge synchronization failed', ['payment_no' => $payment->payment_no, 'error' => $exception->getMessage()]);
            if ($throwOnFailure) {
                throw $exception;
            }

            return $payment->refresh();
        }
    }


    public function syncWechatRecharge(Payment $payment, WechatPayGateway $wechat, bool $throwOnFailure = false): Payment
    {
        $payment->refresh();
        if ($payment->gateway !== 'wechat' || $payment->status !== 'pending' || $payment->order_id !== null || ! $wechat->isConfigured()) {
            return $payment;
        }
        if (! Cache::add('payments:wechat:query:'.$payment->id, true, 10)) {
            return $payment;
        }

        try {
            $result = $wechat->query($payment->payment_no);
            if ($wechat->validateTransaction($payment, $result)) {
                return $this->completeRecharge($payment, (string) $result['transaction_id']);
            }
        } catch (\Throwable $exception) {
            Log::warning('Wechat recharge synchronization failed', ['payment_no' => $payment->payment_no, 'error' => $exception->getMessage()]);
            if ($throwOnFailure) {
                throw $exception;
            }
        }

        return $payment->refresh();
    }


    public function completePayment(Payment $payment, string $transaction): Payment
    {
        return $payment->order_id !== null
            ? app(OrderPaymentService::class)->capture($payment,$transaction)
            : $this->completeRecharge($payment,$transaction);
    }

    public function completeRecharge(Payment $payment, ?string $transactionId = null): Payment
    {
        abort_unless($payment->order_id === null,422,'订单支付不能作为充值入账。');
        return DB::transaction(function () use ($payment, $transactionId): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === 'succeeded') {
                return $locked;
            }
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => '该支付单当前状态不可支付。']);
            }

            $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            $newBalance = Money::add($user->balance, $locked->amount);
            $user->update(['balance' => $newBalance]);

            $locked->update([
                'status' => 'succeeded',
                'transaction_id' => $transactionId ?: 'SIM-'.strtoupper(uniqid()),
                'paid_at' => now(),
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'type' => 'recharge',
                'amount' => $locked->amount,
                'balance_after' => $newBalance,
                'reference_type' => Payment::class,
                'reference_id' => $locked->id,
                'description' => '账户充值 '.$locked->payment_no,
            ]);

            $notice = \App\Models\Notice::firstOrCreate(['dedupe_key' => 'recharge_succeeded:'.$locked->id], [
                'user_id' => $user->id, 'type' => 'recharge_succeeded', 'title' => '账户充值已到账',
                'body' => '充值单 '.$locked->payment_no.' 已到账，金额 '.Money::format($locked->amount).'。',
                'data' => ['payment_id' => $locked->id],
            ]);
            if ($notice->wasRecentlyCreated) {
                DB::afterCommit(fn () => app(\App\Services\Notifications\NotificationDispatcher::class)->dispatch($notice));
            }

            return $locked;
        });
    }

    public function refund(Order $order, string $amount, string $reason): void
    {
        $this->assertAmount($amount);

        DB::transaction(function () use ($order, $amount, $reason): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'paid' && $locked->status !== 'partially_refunded') {
                throw ValidationException::withMessages(['refund' => '只有已支付订单可以退款。']);
            }

            $refundable = Money::subtract($locked->total, $locked->refunded_amount);

            if (Money::compare($amount, $refundable) > 0) {
                throw ValidationException::withMessages(['refund' => '退款金额不能超过可退金额。']);
            }

            $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            $newBalance = Money::add($user->balance, $amount);
            $user->update(['balance' => $newBalance]);

            $refunded = Money::add($locked->refunded_amount, $amount);
            $fullyRefunded = Money::compare($refunded, $locked->total) >= 0;

            $locked->update([
                'refunded_amount' => $refunded,
                'refunded_at' => now(),
                'status' => $fullyRefunded ? 'refunded' : 'partially_refunded',
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'type' => 'refund',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => Order::class,
                'reference_id' => $locked->id,
                'description' => '订单退款 '.$locked->order_no.'：'.$reason,
            ]);
        });
    }

    private function assertGateway(string $gateway): void
    {
        if (! in_array($gateway, ['alipay', 'wechat', 'alipay_sim', 'wechat_sim', 'manual'], true)) {
            throw ValidationException::withMessages(['gateway' => '不支持的充值渠道。']);
        }
    }

    private function assertAmount(string $amount): void
    {
        if (! is_numeric($amount) || Money::compare($amount, '0.01') < 0) {
            throw ValidationException::withMessages(['amount' => '金额必须大于 0。']);
        }
    }
}
