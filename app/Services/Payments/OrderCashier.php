<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use RuntimeException;

class OrderCashier
{
    public function __construct(private OrderPaymentService $orders, private PaymentService $payments, private WechatPayGateway $wechat, private AlipayGateway $alipay) {}

    public function start(User $user, Order $order, string $gateway): array
    {
        abort_unless((int)$order->user_id===(int)$user->id,404);
        abort_unless(in_array($gateway,['alipay','wechat','balance'],true),422);
        $file=fopen(storage_path('app/private/order-cashier-'.$order->id.'.lock'),'c');
        if(!$file||!flock($file,LOCK_EX|LOCK_NB)){
            if(is_resource($file)){fclose($file);}throw new RuntimeException('收银台正在处理，请稍后再试。');
        }
        try {
            $order->refresh();
            if($order->status==='paid'){return $this->state($order);}
            $this->orders->assertPayable($user,$order);
            if($gateway!=='balance'){
                abort_unless($gateway==='wechat'?$this->wechat->isConfigured():$this->alipay->isConfigured(),422,'该支付渠道暂不可用，请选择其他方式。');
                abort_unless(Money::compare($order->total,'0.01')>=0,422,'零金额订单请使用余额确认。');
            }
            $active=null;
            foreach(Payment::where('order_id',$order->id)->where('status','pending')->orderBy('id')->get() as $payment){
                $this->syncPayment($payment);
                if($order->fresh()->status==='paid'){return $this->state($order->fresh());}
                if($payment->fresh()->status!=='pending'){continue;}
                if($payment->gateway===$gateway&&$this->qrCurrent($payment)){$active=$payment;continue;}
                $this->closePayment($payment);
                if($order->fresh()->status==='paid'){return $this->state($order->fresh());}
            }
            if($gateway==='balance'){$this->orders->balance($user,$order);return $this->state($order->fresh());}
            if($active){return $this->state($order->fresh());}
            $payment=DB::transaction(function()use($order,$user,$gateway):Payment {
                $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status==='pending',422,'订单状态已更新，请刷新。');
                return Payment::create(['payment_no'=>DocumentNumber::make('PAY'),'user_id'=>$user->id,'order_id'=>$locked->id,'invoice_id'=>$locked->invoices()->value('id'),'gateway'=>$gateway,'amount'=>$locked->total,'status'=>'pending','payload'=>['source'=>'order_cashier']]);
            });
            if($gateway==='wechat'){$created=$this->wechat->createNative($payment,'订单支付 '.$order->order_no);$qr=(string)$created['code_url'];$extra=[];}
            else{
                $created=$this->alipay->createQrPayment($payment,'订单支付 '.$order->order_no);$qr=(string)$created['qr_code'];$extra=[];
                if(($created['fallback']??false)===true){$extra['page_pay_url']=$qr;$qr=route('payments.alipay.qr',$payment->payment_no);}
            }
            $payment->refresh();$payment->update(['payload'=>array_merge((array)$payment->payload,$extra,['qr_code'=>$qr,'expires_at'=>now()->addMinutes(15)->toIso8601String()])]);
            return $this->state($order->refresh());
        }finally{flock($file,LOCK_UN);fclose($file);}
    }

    public function reconcile(Order $order): void
    {
        if(!Cache::add('order-cashier:query:'.$order->id,true,8)){return;}
        foreach(Payment::where('order_id',$order->id)->where('status','pending')->whereIn('gateway',['alipay','wechat'])->limit(5)->get() as $payment){
            try{$this->syncPayment($payment);}catch(\Throwable $e){report($e);}
        }
    }

    public function syncPayment(Payment $payment): array
    {
        if($payment->status==='succeeded'){return ['state'=>'PAID'];}
        if($payment->gateway==='wechat'){
            $result=$this->wechat->query($payment->payment_no);
            $state=(string)($result['trade_state']??'');
            if($state==='SUCCESS'){
                $this->wechat->validateTransaction($payment,$result);
                $this->payments->completePayment($payment,(string)$result['transaction_id']);
            }elseif(in_array($state,['CLOSED','REVOKED','PAYERROR'],true)){$this->cancelLocal($payment);}
        }else{
            $result=$this->alipay->query($payment->payment_no);$state=(string)($result['trade_status']??'');
            if(in_array($state,['TRADE_SUCCESS','TRADE_FINISHED'],true)){
                if((string)($result['out_trade_no']??'')!==$payment->payment_no||!is_numeric($result['total_amount']??null)||Money::compare($payment->amount,(string)$result['total_amount'])!==0||empty($result['trade_no'])){throw new RuntimeException('支付宝订单金额或交易号不匹配。');}
                $this->payments->completePayment($payment,(string)$result['trade_no']);
            }elseif($state==='TRADE_CLOSED'){$this->cancelLocal($payment);}
            elseif(($result['sub_code']??'')==='ACQ.TRADE_NOT_EXIST'){$state='NOT_FOUND';}
            elseif(($result['code']??'')!=='10000'){throw new RuntimeException('支付宝订单状态暂未确认。');}
        }
        return ['state'=>$state];
    }

    public function closePayment(Payment $payment): void
    {
        $remote=$this->syncPayment($payment);$payment->refresh();
        if($payment->status==='succeeded'||$payment->status==='cancelled'){return;}
        if($remote['state']==='NOT_FOUND'){$this->cancelLocal($payment);return;}
        if(!in_array($remote['state'],['NOTPAY','WAIT_BUYER_PAY'],true)){throw new RuntimeException('原订单正在付款或状态未知，请稍后再切换支付方式。');}
        if($payment->gateway==='wechat'){$this->wechat->closeUnpaid($payment->payment_no);}else{$this->alipay->closeUnpaid($payment->payment_no);}
        $this->syncPayment($payment);$payment->refresh();
        if(!in_array($payment->status,['succeeded','cancelled'],true)){throw new RuntimeException('原付款尚未确认关闭，请稍后再试。');}
    }

    public function close(User $user,Order $order): array
    {
        abort_unless((int)$order->user_id===(int)$user->id,404);
        foreach(Payment::where('order_id',$order->id)->where('status','pending')->whereIn('gateway',['alipay','wechat'])->get() as $payment){$this->closePayment($payment);}
        return $this->state($order->refresh());
    }

    private function cancelLocal(Payment $payment): void
    {
        Payment::whereKey($payment->id)->where('status','pending')->update(['status'=>'cancelled']);$payment->refresh();
    }

    private function qrCurrent(Payment $payment): bool
    {
        return (string)data_get($payment->payload,'qr_code','')!=='' && Carbon::parse((string)data_get($payment->payload,'expires_at','2000-01-01'))->isFuture();
    }

    public function state(Order $order): array
    {
        $active=Payment::where('order_id',$order->id)->whereIn('gateway',['alipay','wechat'])->where('status','pending')->latest('id')->first();
        return ['order_id'=>$order->id,'order_status'=>$order->status,'paid'=>$order->status==='paid','amount'=>(string)$order->total,'payment_id'=>$active?->id,'gateway'=>$active?->gateway,'qr_code'=>$active&&$this->qrCurrent($active)?(string)data_get($active->payload,'qr_code',''):'','expires_at'=>data_get($active?->payload,'expires_at'),'services_url'=>route('console.services.index')];
    }
}
