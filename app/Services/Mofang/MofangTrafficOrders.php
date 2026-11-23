<?php
namespace App\Services\Mofang;
use App\Models\Service;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\UpstreamOrder;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
class MofangTrafficOrders
{
    public function packets(Service $service, array $detail): array
    {
        $rows=(array)data_get($detail,'data.dcim.flowpacket',data_get($detail,'data.flowpacket',[]));$overrides=(array)data_get($service->specs,'traffic_package_prices',[]);$markup=max(0,(float)data_get($service->product?->metadata,'markup_percent',config('mofang.default_markup_percent',20)));
        $packets=[];foreach($rows as $packet){if(!is_array($packet)||!is_numeric($packet['id']??null)||!is_numeric($packet['price']??null)){continue;}$id=(string)$packet['id'];$packet['upstream_price']=(float)$packet['price'];$packet['price']=isset($overrides[$id])?(float)$overrides[$id]:round((float)$packet['price']*(1+$markup/100),2);$packet['markup_percent']=$markup;$packets[]=$packet;}
        return $packets;
    }
    public function create(Service $service, array $packet): Order
    {
        return DB::transaction(function()use($service,$packet):Order {
            $service->user()->lockForUpdate()->firstOrFail();
            $existing=Order::where('user_id',$service->user_id)->where('type','traffic')->where('status','pending')->whereHas('items',fn($q)=>$q->where('service_id',$service->id)->where('configuration->flow_packet_id',(int)$packet['id']))->latest('id')->first();if($existing){return $existing;}
            $amount=number_format((float)$packet['price'],2,'.','');abort_if((float)$amount<=0,422,'流量包价格无效。');
            $order=Order::create(['order_no'=>DocumentNumber::make('ORD'),'user_id'=>$service->user_id,'type'=>'traffic','status'=>'pending','subtotal'=>$amount,'discount'=>0,'tax'=>0,'total'=>$amount,'currency'=>'CNY']);
            OrderItem::create(['order_id'=>$order->id,'product_id'=>$service->product_id,'service_id'=>$service->id,'product_name'=>$service->name.' · '.$packet['name'],'billing_cycle'=>'onetime','unit_price'=>$amount,'quantity'=>1,'amount'=>$amount,'configuration'=>['flow_packet_id'=>(int)$packet['id'],'capacity'=>$packet['capacity']??null,'upstream_price'=>$packet['upstream_price']]]);
            Invoice::create(['invoice_no'=>DocumentNumber::make('INV'),'user_id'=>$service->user_id,'order_id'=>$order->id,'status'=>'unpaid','amount'=>$amount,'paid_amount'=>0,'due_at'=>now()->addDays(3),'items'=>[['name'=>$packet['name'],'amount'=>$amount]]]);return $order;
        });
    }
    public function paid(Order $order): void
    {
        foreach($order->items as $item){$service=Service::where('user_id',$order->user_id)->findOrFail($item->service_id);abort_unless($service->product?->upstream_source==='mofang'&&str_starts_with((string)$service->external_ref,'mofang:')&&in_array($service->status,['active','stopped','suspended'],true),422,'当前实例无法购买流量包。');
            UpstreamOrder::firstOrCreate(['order_id'=>$order->id,'service_id'=>$service->id,'type'=>'traffic'],['product_id'=>$service->product_id,'source'=>'mofang','status'=>'pending','amount'=>$item->amount,'billing_cycle'=>'onetime','upstream_service_id'=>substr($service->external_ref,7),'payload'=>['flow_packet_id'=>(int)data_get($item->configuration,'flow_packet_id'),'capacity'=>data_get($item->configuration,'capacity')]]);
        }
    }
}
