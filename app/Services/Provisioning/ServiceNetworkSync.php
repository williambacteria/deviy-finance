<?php

namespace App\Services\Provisioning;

use App\Models\NatMapping;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceNetworkSync
{
    public function __construct(private ControllerGateway $gateway) {}

    public function sync(Service $service, bool $ensureRemote = false): void
    {
        $service->loadMissing(['node','product']);
        if($service->product?->upstream_source!=='local' || !in_array($service->product?->type,['nat_vm','cloud_vm'],true) || ($service->product?->type!=='nat_vm' && data_get($service->product?->metadata,'controller.network_mode')!=='private')){return;}
        if(!in_array($service->status,['active','provisioning','stopped'],true)||data_get($service->specs,'legacy_import.operation_hold',false)){return;}
        $nodeId=(string)($service->node?->agent_node_id?:data_get($service->specs,'controller.node_id')?:data_get($service->product?->metadata,'controller.node_id'));
        $reference=preg_replace('/^(?:vmid:|id:)/','',trim((string)$service->external_ref));
        if($nodeId===''||$reference===''){return;}
        $lock=fopen(storage_path('app/private/network-sync-'.hash('sha256',$nodeId).'.lock'),'c');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock)){fclose($lock);}throw new RuntimeException('节点网络同步正在执行，请稍后重试。');}
        try {
            $vm=collect($this->gateway->virtualMachines($nodeId))->first(fn(array $item):bool=>(string)($item['id']??'')===$reference||(string)($item['vmid']??'')===$reference);
            $internal=trim((string)($vm['ip']??''));
            if(!$vm||!filter_var($internal,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){throw new RuntimeException('未取得与本实例绑定一致的主控 IPv4，保留原网络记录。');}
            $node=$this->gateway->node($nodeId);
            $public=trim((string)($node['node_ip']??''));
            if(!$this->publicIPv4($public)){throw new RuntimeException('主控节点公网地址必须是有效公网 IPv4，不能使用域名或内网地址。');}
            $windows=str_contains(strtolower((string)$service->os),'windows')||str_starts_with(strtolower((string)data_get($service->specs,'ostype','')),'win');
            $internalPort=$windows?3389:22;
            $all=$this->gateway->natRules($nodeId);
            $rules=$this->owned($all,$internal,$public);
            $tcp=collect($rules)->first(fn(array $r):bool=>(int)$r['internal_port']===$internalPort&&in_array(strtolower($r['protocol']),['tcp','all','tcp+udp'],true));
            $changed=false;
            if(!$tcp&&$ensureRemote){
                $minimum=(int)($node['port_min']??0);$maximum=(int)($node['port_max']??0);
                if($minimum<1||$maximum<$minimum||$maximum>65535){throw new RuntimeException('主控节点没有有效的可分配公网端口范围。');}
                $this->gateway->allocateNat($nodeId,['external_ip'=>$public,'internal_ip'=>$internal,'internal_port'=>$internalPort,'protocol'=>'tcp','port_min'=>$minimum,'port_max'=>$maximum,'exclude_ports'=>(string)($node['exclude_ports']??'')]);
                $changed=true;$all=$this->gateway->natRules($nodeId);$rules=$this->owned($all,$internal,$public);
                $tcp=collect($rules)->first(fn(array $r):bool=>(int)$r['internal_port']===$internalPort&&strtolower($r['protocol'])==='tcp');
            }
            if(!$tcp){throw new RuntimeException('远程连接的 TCP 公网映射尚未就绪，请稍后同步。');}
            $port=(int)$tcp['external_port'];
            $hasUdp=collect($rules)->contains(fn(array $r):bool=>(int)$r['external_port']===$port&&(int)$r['internal_port']===$internalPort&&in_array(strtolower($r['protocol']),['udp','all','tcp+udp'],true));
            if($windows&&$ensureRemote&&!$hasUdp){
                $conflict=collect($all)->contains(fn(array $r):bool=>(string)($r['external_ip']??'')===$public&&(int)($r['external_port']??0)===$port&&in_array(strtolower((string)($r['protocol']??'')),['udp','all','tcp+udp'],true));
                if($conflict){throw new RuntimeException('远程连接 UDP 端口与其他实例冲突，未覆盖原规则。');}
                $this->gateway->createNatRule($nodeId,['external_ip'=>$public,'external_port'=>$port,'internal_ip'=>$internal,'internal_port'=>$internalPort,'protocol'=>'udp','status'=>'pending']);$changed=true;
                $rules=$this->owned($this->gateway->natRules($nodeId),$internal,$public);
            }
            $signature=hash('sha256',json_encode(array_map(fn(array $r):array=>[$r['external_ip'],(int)$r['external_port'],$r['internal_ip'],(int)$r['internal_port'],strtolower($r['protocol'])],$rules)));
            if($ensureRemote&&($changed||data_get($service->specs,'controller.network_signature')!==$signature)){$this->gateway->applyNetwork($nodeId,'nat');}
            $display=trim((string)(data_get($service->product?->metadata,'controller.public_host')?:$service->node?->host?:$public));
            if($display!==$public&&gethostbyname($display)!==$public){$display=$public;}
            DB::transaction(function()use($service,$rules,$internal,$public,$port,$display,$windows,$signature,$ensureRemote):void {
                $current=Service::whereKey($service->id)->lockForUpdate()->firstOrFail();$specs=(array)$current->specs;$specs['internal_ip']=$internal;
                $specs['remote']=array_merge((array)($specs['remote']??[]),['address'=>$display,'port'=>$port,'protocol'=>$windows?'rdp':'ssh','username'=>(string)data_get($specs,'remote.username',$windows?'Administrator':'root')]);
                $specs['controller']=array_merge((array)($specs['controller']??[]),['public_ip'=>$public,'public_host'=>$display]);
                if($ensureRemote){$specs['controller']['network_signature']=$signature;$specs['controller']['network_applied_at']=now()->toIso8601String();}
                unset($specs['network_sync_error']);$ids=[];
                foreach(collect($rules)->groupBy(fn(array $r):string=>(int)$r['external_port'].'|'.(int)$r['internal_port']) as $group){
                    $first=$group->first();$protocols=$group->pluck('protocol')->map(fn($p)=>strtolower((string)$p));$both=$protocols->contains('all')||$protocols->contains('tcp+udp')||($protocols->contains('tcp')&&$protocols->contains('udp'));
                    $mapping=NatMapping::updateOrCreate(['service_id'=>$current->id,'external_port'=>(int)$first['external_port'],'internal_ip'=>$internal,'internal_port'=>(int)$first['internal_port']],['external_ip'=>$public,'protocol'=>$both?'tcp+udp':$protocols->first(),'status'=>'active']);$ids[]=$mapping->id;
                }
                NatMapping::where('service_id',$current->id)->whereNotIn('id',$ids)->delete();$ports=array_column($rules,'external_port');
                $current->update(['nat_ip'=>$public,'ip_address'=>$internal,'nat_port_start'=>min($ports),'nat_port_end'=>max($ports),'specs'=>$specs,'provision_status'=>$ensureRemote&&in_array($current->provision_status,['network_pending','network_failed'],true)?'succeeded':$current->provision_status]);
            });$service->refresh()->load('natMappings');
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    private function publicIPv4(string $ip): bool
    {
        return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false && !str_starts_with($ip,'203.0.113.');
    }

    private function owned(array $all,string $internal,string $public): array
    {
        return array_values(array_filter($all,fn(array $rule):bool=>(string)($rule['internal_ip']??'')===$internal&&(string)($rule['external_ip']??'')===$public&&(int)($rule['external_port']??0)>0&&(int)($rule['external_port']??0)<=65535&&(int)($rule['internal_port']??0)>0&&(int)($rule['internal_port']??0)<=65535&&in_array(strtolower((string)($rule['protocol']??'')),['tcp','udp','tcp+udp','all'],true)));
    }
}
