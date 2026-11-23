<?php
namespace App\Services\Reseller;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Console\OrderController;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ResellerAccount;
use App\Models\ResellerCartItem;
use App\Models\Service;
use App\Services\Billing\RenewalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ResellerCommerce
{
    public function __construct(private ResellerCatalog $catalog, private RenewalService $renewals) {}
    public function add(Request $request, ResellerAccount $account): array
    {
        $data=$request->validate(['pid'=>['required','integer','min:1'],'qty'=>['sometimes','integer','min:1','max:10'],'billingcycle'=>['required','in:monthly,quarterly,semiannually,annually'],'configoption'=>['sometimes','array','max:30'],'configoption.*'=>['required'],'request_id'=>['nullable','string','max:128']]);
        $product=$this->catalog->product((int)$data['pid']);
        abort_unless($product->isPurchasable(),422,'商品暂不可购买。');
        abort_unless($product->prices->contains('billing_cycle',$data['billingcycle']),422,'所选周期不可购买。');
        $raw=(array)($data['configoption']??[]);
        abort_if(array_filter($raw,fn($v)=>!is_scalar($v)||mb_strlen((string)$v)>80),422,'配置值无效。');
        $selection=$this->catalog->selection($product,$raw);
        $payload=['pid'=>$product->id,'quantity'=>(int)($data['qty']??1),'billing_cycle'=>$data['billingcycle']]+$selection;
        $sort=function(array &$values)use(&$sort):void{ksort($values);foreach($values as &$value){if(is_array($value)){$sort($value);}}};$sort($payload);
        $hash=hash('sha256',json_encode($payload));
        $key=(string)($request->header('Idempotency-Key')?:($data['request_id']??Str::uuid()));
        $cart=ResellerCartItem::firstOrCreate(['reseller_account_id'=>$account->id,'request_key'=>hash('sha256',$key)],['payload'=>$payload,'payload_hash'=>$hash,'expires_at'=>now()->addDay()]);
        abort_unless(hash_equals($cart->payload_hash,$hash),409,'相同幂等键不能用于不同订单配置。');
        return ['i'=>$cart->id,'position'=>$cart->id,'expires_at'=>$cart->expires_at->timestamp];
    }
    public function settle(Request $request, ResellerAccount $account): array
    {
        $data=$request->validate(['pos'=>['required','array','size:1'],'pos.*'=>['required','integer','min:1'],'payment'=>['nullable','in:Credit,credit,balance']]);
        $cart=ResellerCartItem::where('reseller_account_id',$account->id)->findOrFail($data['pos'][0]);
        if(!$cart->order_id){
            abort_if($cart->expires_at->isPast(),422,'购物车已过期，请重新加购。');
            DB::transaction(function()use($request,$account,$cart):void {
                $cart=ResellerCartItem::whereKey($cart->id)->lockForUpdate()->firstOrFail();
                if($cart->order_id){return;}
                $payload=$cart->payload;$product=$this->catalog->product((int)$payload['pid']);
                $sub=$this->request($request,$account,$payload);
                $response=app()->call([app(CheckoutController::class),'store'],['request'=>$sub,'product'=>$product]);
                preg_match('#/console/orders/(\d+)$#',(string)parse_url($response->getTargetUrl(),PHP_URL_PATH),$matches);
                abort_unless(isset($matches[1]),500,'订单创建未返回订单号。');
                $cart->update(['order_id'=>(int)$matches[1]]);
            });
            $cart->refresh();
        }
        $order=Order::where('user_id',$account->user_id)->findOrFail($cart->order_id);
        $this->pay($request,$account,$order);
        return $this->result($order);
    }
    public function applyCredit(Request $request, ResellerAccount $account): array
    {
        $id=$request->input('invoiceid',$request->input('id'));
        abort_unless(is_scalar($id)&&ctype_digit((string)$id),422,'请提供有效账单 ID。');
        $invoice=Invoice::where('user_id',$account->user_id)->findOrFail($id);
        $order=Order::where('user_id',$account->user_id)->findOrFail($invoice->order_id);
        $this->pay($request,$account,$order);
        return $this->result($order);
    }
    public function pay(Request $request, ResellerAccount $account, Order $order): void
    {
        $order->refresh();
        if($order->status==='paid'){return;}
        app()->call([app(OrderController::class),'pay'],['request'=>$this->request($request,$account,[]),'order'=>$order]);
    }
    public function result(Order $order): array
    {
        $order->refresh();
        return ['orderid'=>$order->id,'order_id'=>$order->id,'invoiceid'=>$order->invoices()->value('id'),'hostid'=>Service::where('order_id',$order->id)->pluck('id')->all(),'paid'=>$order->status==='paid','amount'=>(string)$order->total];
    }
    public function request(Request $original, ResellerAccount $account, array $data): Request
    {
        $request=Request::create($original->url(),'POST',$data);$request->attributes->set('reseller_api_request',true);$request->setUserResolver(fn()=>$account->user->fresh());
        if($original->hasSession()){$request->setLaravelSession($original->session());}
        return $request;
    }
    public function renew(Request $request, ResellerAccount $account, Service $service): array
    {
        abort_unless(in_array($service->status,['active','stopped','suspended'],true),422,'当前状态不允许续费。');
        $cycle=$request->input('billingcycles',$request->input('billing_cycle',$service->billing_cycle));
        abort_unless(is_string($cycle)&&in_array($cycle,['monthly','quarterly','semiannually','annually'],true),422,'续费周期无效。');
        $order=$this->renewals->createOrder($service,$cycle);
        return $this->result($order);
    }
}
