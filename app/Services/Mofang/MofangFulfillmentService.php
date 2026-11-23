<?php
namespace App\Services\Mofang;
use App\Models\UpstreamOrder;
use App\Models\Service;
use App\Services\Mofang\Contracts\MofangGateway;
use App\Support\UpstreamExpiry;
use App\Support\Billing;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
class MofangFulfillmentService
{
    public function __construct(private readonly MofangGateway $gateway) {}
    public function process(UpstreamOrder $order): void
    {
        $file=fopen(storage_path('app/private/mofang-fulfillment-'.$order->id.'.lock'),'c');
        if(!$file||!flock($file,LOCK_EX|LOCK_NB)){if(is_resource($file)){fclose($file);}return;}
        try {
            $order->refresh();
            if(in_array($order->status,['active','suspended','failed','needs_review'],true)){return;}
            if($order->order?->status!=='paid'){throw new RuntimeException('本站订单未付款，禁止提交上游。');}
            if(!$order->service){throw new RuntimeException('本地实例不存在。');}
            $payload=(array)$order->payload;
            if(empty($payload['remote_invoice_id'])){
                if(!empty($payload['create_submitted_at'])){
                    $this->review($order,'上次创建账单结果未取得，必须核对上游账单再填写关联，禁止重复下单。');return;
                }
                $baseline=null;
                if($order->type!=='purchase'){
                    if(!$order->upstream_service_id){throw new RuntimeException('上游实例 ID 缺失。');}
                    $detail=$this->gateway->fetchService($order->upstream_service_id);
                    $this->assertHost($order,$detail);
                    if($order->type==='renewal'){
                        $baseline=UpstreamExpiry::from($detail);if(!$baseline){throw new RuntimeException('无法获取上游续费前到期时间，未创建续费账单。');}
                        $payload['expires_before']=$baseline->timestamp;
                    }elseif($order->type==='traffic'){$payload['traffic_before']=$this->trafficSnapshot($detail);}
                }
                $payload['create_submitted_at']=now()->toIso8601String();$order->update(['payload'=>$payload,'last_error'=>null]);
                $created=match($order->type){
                    'renewal'=>$this->gateway->renewService($order->upstream_service_id,['billing_cycle'=>$order->billing_cycle,'months'=>Billing::months($order->billing_cycle?:'monthly'),'invoice_only'=>true]),
                    'traffic' => $this->gateway->buyTrafficPackage($order->upstream_service_id, [
                        'flow_packet_id' => (int) ($payload['flow_packet_id'] ?? 0),
                        'idempotency_key' => 'deviy-traffic-'.$order->order->order_no,
                    ]),
                    default=>$this->gateway->createOrder($payload),
                };
                $invoice=(string)(data_get($created,'invoice_id')??data_get($created,'billing_id')??data_get($created,'data.invoiceid')??data_get($created,'data.invoice_id')??'');
                if(!ctype_digit($invoice)||((int)$invoice)<1){$this->review($order,'上游已响应但未返回有效账单 ID，请核对上游后处理，禁止重复创建。');return;}
                $payload['remote_invoice_id']=$invoice;$payload['remote_order_id']=(string)(data_get($created,'order_id')??data_get($created,'data.orderid')??'');
                $serviceId=(string)(data_get($created,'service_id')??data_get($created,'data.service_id')??data_get($created,'data.hostid.0')??'');
                $order->update(['payload'=>$payload,'upstream_order_id'=>$payload['remote_order_id']?:$invoice,'upstream_service_id'=>$order->upstream_service_id?:($serviceId?:null),'status'=>'ordered','last_synced_at'=>now()]);
            }
            $payload=(array)$order->payload;
            if(empty($payload['payment_submitted_at'])){
                $payload['payment_submitted_at']=now()->toIso8601String();$order->update(['payload'=>$payload]);
                try {
                    $paid=$this->gateway->payOrder((string)$payload['remote_invoice_id']);
                    $payload['payment_acknowledged_at']=now()->toIso8601String();
                    $host=(string)(data_get($paid,'service_id')??data_get($paid,'data.service_id')??'');
                    $order->update(['payload'=>$payload,'status'=>'paid','upstream_service_id'=>$order->upstream_service_id?:($host?:null),'last_error'=>null,'last_synced_at'=>now()]);
                }catch(Throwable $e){
                    $payload['payment_error']=mb_substr($e->getMessage(),0,800);$order->update(['payload'=>$payload,'status'=>'confirming','last_error'=>'上游付款结果未确认：'.$payload['payment_error'],'last_synced_at'=>now()]);
                }
            }
            $order->refresh();$payload=(array)$order->payload;
            if($order->type==='purchase'&&!$order->upstream_service_id){
                $host=$this->gateway->findServiceId((string)$payload['remote_invoice_id'],(string)($payload['customer_email']??''),(string)($payload['product_id']??''));
                if($host){$order->update(['upstream_service_id'=>$host]);}
            }
            if(!$order->upstream_service_id){$this->pending($order,'等待上游账单对应实例，暂不按相同商品或邮箱猜测绑定。');return;}
            $detail=$this->gateway->fetchService($order->upstream_service_id);$this->assertHost($order,$detail);
            if($order->type==='renewal'){
                $expires=UpstreamExpiry::from($detail);$before=(int)($payload['expires_before']??0);
                if(!$expires||!$before||$expires->timestamp<=$before){$this->pending($order,'付款已提交，等待上游到期时间变化；保留原到期时间，未重复创建或支付。');return;}
                if(empty($payload['payment_acknowledged_at'])){$this->review($order,'上游日期发生变化，但付款响应缺失，请核对原账单付款状态后确认。');return;}
                DB::transaction(function()use($order,$detail,$expires):void {
                    app(MofangServiceSync::class)->sync($order->service,$detail);
                    $order->service->update(['last_renewed_at'=>now(),'grace_ends_at'=>null,'provision_status'=>'success']);
                    $order->update(['status'=>'active','upstream_expires_at'=>$expires,'last_error'=>null,'last_synced_at'=>now()]);
                });return;
            }
            if($order->type==='traffic'){
                if(empty($payload['payment_acknowledged_at'])){$this->pending($order,'流量包账单付款状态未确认。');return;}
                $after=$this->trafficSnapshot($detail);$before=(array)($payload['traffic_before']??[]);
                if (! $this->trafficDelivered($before, $after, (float) ($payload['capacity'] ?? 0))) {
                    $this->pending($order, '流量包已提交付款，等待上游套餐或容量到账确认。');
                    return;
                }
                $payload['traffic_after']=$after;$order->update(['payload'=>$payload,'status'=>'active','last_error'=>null,'last_synced_at'=>now()]);return;
            }
            $host=(array)data_get($detail,'data.host_data',[]);$expires=UpstreamExpiry::from($detail);
            if(empty($payload['payment_acknowledged_at'])||strtolower((string)($host['domainstatus']??''))!=='active'||!$expires){$this->pending($order,'等待上游付款、开通状态与到期时间完整确认。');return;}
            DB::transaction(function()use($order,$detail,$expires):void {
                $service=Service::whereKey($order->service_id)->lockForUpdate()->firstOrFail();
                $service->update(['external_ref'=>'mofang:'.$order->upstream_service_id]);
                app(MofangServiceSync::class)->sync($service,$detail);
                $service->update(['status'=>'active','provision_status'=>'success']);
                DB::afterCommit(function () use ($service): void {
                    $service->loadMissing('user');
                    if ($service->user) {
                        \App\Services\Mail\MailSettings::sendTemplate('service_provisioned', $service->user, ['service_name' => $service->name, 'service_no' => $service->service_no, 'date' => $service->expires_at?->format('Y-m-d H:i'), 'console_url' => route('console.services.show', $service)]);
                    }
                });
                $order->update(['status'=>'active','upstream_expires_at'=>$expires,'last_error'=>null,'last_synced_at'=>now()]);
            });
        }catch(Throwable $e){
            $order->refresh();$attempts=$order->attempts+1;$payload=(array)$order->payload;
            $uncertain=!empty($payload['create_submitted_at'])&&empty($payload['remote_invoice_id']);
            $order->update(['attempts'=>$attempts,'status'=>$uncertain||$attempts>=20?'needs_review':(!empty($payload['remote_invoice_id'])?'confirming':'pending'),'last_error'=>mb_substr($e->getMessage(),0,1000),'last_synced_at'=>now()]);
            report($e);
            if($order->type==='purchase'){$order->service?->update(['provision_status'=>'upstream_confirmation']);}
        }finally{flock($file,LOCK_UN);fclose($file);}
    }
    private function assertHost(UpstreamOrder $order,array $detail): void
    {
        $id=(string)data_get($detail,'data.host_data.id','');
        if($id===''||$id!==$order->upstream_service_id){throw new RuntimeException('上游主机 ID 与绑定不一致，拒绝操作。');}
        if(Service::where('external_ref','mofang:'.$id)->whereKeyNot($order->service_id)->exists()){throw new RuntimeException('上游实例已绑定其他本地服务，拒绝重复关联。');}
    }
    private function trafficSnapshot(array $detail): array
    {
        return ['packages'=>(array)data_get($detail,'data.dcim.flow_packet_use_list',[]),'capacity'=>data_get($detail,'data.host_data.bwlimit')];
    }
    private function trafficDelivered(array $before, array $after, float $capacity): bool
    {
        if ($before === []) {
            return false;
        }

        // Usage and remaining allowance change without a new purchase.
        $countPackages = static fn (array $snapshot): int => count(array_filter(
            (array) ($snapshot['packages'] ?? []),
            static fn ($packet): bool => is_array($packet) && ($capacity <= 0 || (float) ($packet['capacity'] ?? 0) === $capacity),
        ));
        $oldLimit = $before['capacity'] ?? null;
        $newLimit = $after['capacity'] ?? null;

        return $countPackages($after) > $countPackages($before)
            || ($capacity > 0 && is_numeric($oldLimit) && is_numeric($newLimit) && (float) $newLimit - (float) $oldLimit >= $capacity);
    }

    private function pending(UpstreamOrder $order,string $reason): void
    {
        $attempts=$order->attempts+1;$order->update(['status'=>$attempts>=20?'needs_review':'confirming','attempts'=>$attempts,'last_error'=>$reason,'last_synced_at'=>now()]);
    }
    private function review(UpstreamOrder $order,string $reason): void
    {
        $order->update(['status'=>'needs_review','last_error'=>$reason,'last_synced_at'=>now()]);
    }
}
