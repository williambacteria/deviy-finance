<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\AlipayGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WechatPayGateway;
use App\Support\Money;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('payments:sync {--limit=50}')]
#[Description('主动查询支付宝/微信待支付单并补单入账')]
class PaymentSyncCommand extends Command
{
    public function handle(AlipayGateway $alipay, WechatPayGateway $wechat, PaymentService $payments): int
    {
        $paid = 0;
        $errors = 0;
        $limit = max(1, (int) $this->option('limit'));

        if ($alipay->isConfigured()) {
            foreach (Payment::query()->where('gateway', 'alipay')->whereNull('order_id')->where('status', 'pending')->where('created_at', '<', now()->subSeconds(15))->latest()->limit($limit)->get() as $payment) {
                try {
                    $updated = $payments->syncAlipayRecharge($payment, $alipay, true);
                    if ($updated->status === 'succeeded') {
                        $paid++;
                    }
                } catch (Throwable $exception) {
                    $errors++;
                }
            }
        }

        if ($wechat->isConfigured()) {
            foreach (Payment::query()->where('gateway', 'wechat')->whereNull('order_id')->where('status', 'pending')->where('created_at', '<', now()->subMinutes(5))->latest()->limit($limit)->get() as $payment) {
                try {
                    $updated = $payments->syncWechatRecharge($payment, $wechat, true);
                    if ($updated->status === 'succeeded') {
                        $paid++;
                    }
                } catch (Throwable $exception) {
                    $errors++;
                    Log::warning('Wechat query failed', ['payment_no' => $payment->payment_no, 'error' => $exception->getMessage()]);
                }
            }
        }


        foreach (Payment::whereNotNull('order_id')->whereIn('gateway',['alipay','wechat'])->where('status','pending')->oldest('updated_at')->limit($limit)->get() as $payment) {
            try {
                app(\App\Services\Payments\OrderCashier::class)->syncPayment($payment);
                if($payment->fresh()->status==='succeeded'){$paid++;}
            }catch(Throwable $exception){$errors++;report($exception);}
        }

        $this->info("主动查单完成：补单 {$paid}，异常 {$errors}。");

        return self::SUCCESS;
    }
}
