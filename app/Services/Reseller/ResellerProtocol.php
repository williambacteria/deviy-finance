<?php
namespace App\Services\Reseller;
use App\Http\Controllers\Console\ServiceController;
use App\Models\ResellerAccount;
use App\Models\Service;
use App\Services\Billing\RenewalService;
use App\Support\ServicePresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
class ResellerProtocol
{
    private int $responseStatus = 200;
    public function __construct(private ResellerAuth $auth, private ResellerCatalog $catalog, private ResellerCommerce $commerce, private RenewalService $renewals) {}
    public function accepts(Request $request): bool
    {
        $token=(string)$request->bearerToken();
        if($token!==''){return str_starts_with($token,'dvy_');}
        if($request->getUser()!==null){return true;}
        if(trim($request->path(),'/')==='zjmf_api_login'){
            $username=$request->input('username');$password=$request->input('password');
            return !is_string($username)||!is_string($password)||mb_strtolower($username)!=='854124946@qq.com'||str_starts_with($password,'dvyk_');
        }
        return true;
    }
    public function handle(Request $request): Response
    {
        $path='/'.trim($request->path(),'/');$id=(string)Str::uuid();$account=null;$lock=null;
        try {
            if($path==='/zjmf_api_login'){
                abort_unless($request->isMethod('POST'),405,'请使用 POST。');
                $data=$request->validate(['username'=>['required','string','max:190'],'password'=>['required','string','max:200']]);
                $account=$this->auth->credentials($request,$data['username'],$data['password']);
                $jwt=$this->auth->issue($account);
                return $this->response(['status'=>200,'msg'=>'登录成功','jwt'=>$jwt,'expires_in'=>7200,'data'=>['jwt'=>$jwt,'expires_in'=>7200]],200,$id);
            }
            $account=$this->auth->authenticate($request);
            $key='reseller-api:'.$account->id;
            abort_if(RateLimiter::tooManyAttempts($key,120),429,'请求超过每分钟120次限制，请稍后重试。');RateLimiter::hit($key,60);
            $write=!$request->isMethod('GET');
            if($write){
                $dir=storage_path('app/private/reseller-locks');if(!is_dir($dir)){mkdir($dir,0700,true);}
                $lock=fopen($dir.'/'.$account->id.'.lock','c');
                abort_unless($lock&&flock($lock,LOCK_EX|LOCK_NB),429,'本账户有请求正在处理，请稍后重试。');
            }
            $result=$this->dispatch($request,$account,$path);
            if(!$account->last_used_at || $account->last_used_at->lt(now()->subMinute())){$account->update(['last_used_at'=>now()]);}
            return $this->response(['status'=>200,'msg'=>'操作成功','data'=>$result],200,$id);
        } catch(ValidationException $e){
            return $this->response(['status'=>422,'msg'=>collect($e->errors())->flatten()->first()??'参数校验失败。','errors'=>$e->errors()],422,$id);
        } catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return $this->response(['status'=>404,'msg'=>'记录不存在或不属于当前账户。'],404,$id);
        } catch(HttpExceptionInterface $e){
            return $this->response(['status'=>$e->getStatusCode(),'msg'=>$e->getMessage()?:'请求不可执行。'],$e->getStatusCode(),$id);
        } catch(\Throwable $e){
            report($e);
            return $this->response(['status'=>503,'msg'=>'处理暂未完成，请先查询订单或实例状态；请勿使用新幂等键重复提交。'],503,$id);
        } finally {
            if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
            if($account){try{DB::table('reseller_api_logs')->insert(['reseller_account_id'=>$account->id,'method'=>$request->method(),'path'=>mb_substr($path,0,190),'ip'=>$request->ip(),'status'=>$this->responseStatus,'request_id'=>$id,'created_at'=>now()]);}catch(\Throwable $ignored){}}
        }
    }
    private function response(array $payload,int $status,string $id): Response
    {
        $payload['request_id']=$id;
        // Status is recorded without request bodies, authorization headers or credentials.
        $this->responseStatus = $status;
        return response($payload,$status)->header('Content-Type','application/json; charset=UTF-8')->header('Cache-Control','no-store')->header('X-Request-ID',$id);
    }
    public function service(ResellerAccount $account, mixed $id): Service
    {
        abort_unless(is_scalar($id)&&ctype_digit((string)$id),422,'请提供有效实例 ID。');
        return Service::with(['product.group.parent','product.prices'])->where('user_id',$account->user_id)->findOrFail($id);
    }
    public function host(Service $service, bool $credentials=false): array
    {
        $cdn=ServicePresentation::kind($service->product)==='cdn';
        $status=match($service->status){'active','stopped'=>'Active','suspended'=>'Suspended','terminated'=>'Deleted',default=>'Pending'};
        try{$amount=$this->renewals->quote($service,$service->billing_cycle)['amount'];}catch(\Throwable){$amount=(string)($service->order?->items()->where('product_id',$service->product_id)->value('unit_price')??'0.00');}
        $data=['id'=>$service->id,'host_id'=>$service->id,'productid'=>$service->product_id,'pid'=>$service->product_id,'orderid'=>$service->order_id,'domain'=>$service->name,'productname'=>$service->product?->name,'groupname'=>$service->product?->group?->name,'type'=>$cdn?'cdn':($service->product?->type==='bare_metal'?'dcim':'cloud'),'domainstatus'=>$status,'status'=>$status,'nextduedate'=>$service->expires_at?->timestamp??0,'nextinvoicedate'=>$service->next_due_at?->timestamp??0,'regdate'=>$service->created_at?->timestamp??0,'billingcycle'=>$service->billing_cycle,'amount'=>$amount,'dedicatedip'=>$cdn?'':($service->ip_address??$service->nat_ip??''),'assignedips'=>[],'os'=>$cdn?'':($service->os??''),'port'=>$cdn?'':data_get($service->specs,'remote.port',22),'auto_terminate_end_cycle'=>!$service->auto_renew,'provision_status'=>$service->provision_status];
        if($credentials&&!$cdn){$data['username']=(string)data_get($service->specs,'remote.username','');$data['password']=(string)data_get($service->specs,'remote.password','');$data['connect_address']=\App\Support\RemoteEndpoint::forService($service)['display'];}
        return $data;
    }
    private function dispatch(Request $request,ResellerAccount $account,string $path): array
    {
        $get=$request->isMethod('GET');
        if($get&&in_array($path,['/api/product','/api/product/proinfo'],true)){
            $info=$this->catalog->products()->map(fn($p)=>$this->catalog->info($p))->all();
            return ['info'=>$info,'list'=>$info,'currency'=>[$this->catalog->currency()]];
        }
        if($get&&(in_array($path,['/api/product/prodetail','/api/product/resource'],true)||preg_match('#^/api/product/(\d+)/resource$#D',$path,$resourceMatch))){
            if(isset($resourceMatch[1])){$request->query->set('pid',$resourceMatch[1]);}
            $ids=$request->input('ids',$request->input('id',$request->input('pid',[])));$ids=is_array($ids)?$ids:explode(',',(string)$ids);
            $detail=[];foreach($this->catalog->products()as $product){if($ids!==[]&&!in_array((string)$product->id,array_map('strval',$ids),true)){continue;}$config=$this->catalog->config($product);$detail[$product->id]=array_merge($config['products'],array_diff_key($config,['products'=>true,'flag'=>true]));}
            return ['detail'=>$detail];
        }
        if($get&&$path==='/cart/get_product_config'){return $this->catalog->config($this->catalog->product($request->integer('pid')));}
        if(!$get&&$request->isMethod('POST')){
            if($path==='/cart/add_to_shop'){return $this->commerce->add($request,$account);}
            if($path==='/cart/settle'){return $this->commerce->settle($request,$account);}
            if($path==='/apply_credit'){return $this->commerce->applyCredit($request,$account);}
        }
        if($get&&$path==='/host/list'){
            $filters=$request->validate(['limit'=>['nullable','integer','min:1','max:100'],'page'=>['nullable','integer','min:1'],'search'=>['nullable','string','max:100']]);
            $rows=Service::where('user_id',$account->user_id)->with(['product.group.parent','product.prices','order'])->when($filters['search']??null,fn($q,$v)=>$q->where(fn($n)=>$n->where('name','like','%'.$v.'%')->orWhere('id',$v)))->orderByDesc('id')->paginate($filters['limit']??20);
            return ['list'=>$rows->getCollection()->map(fn($s)=>$this->host($s))->all(),'total'=>$rows->total(),'page'=>$rows->currentPage(),'limit'=>$rows->perPage(),'credit'=>(string)$account->user->fresh()->balance,'currency'=>$this->catalog->currency()];
        }
        if(($get||$request->isMethod('POST'))&&$path==='/api/host/sync'){
            $service=$this->service($account,$request->input('host_id',$request->input('id')));
            return ['host_data'=>$this->host($service,true)];
        }
        if($get&&in_array($path,['/host/header','/host/dedicatedserver'],true)){
            $service=$this->service($account,$request->input('host_id',$request->input('id')));$this->sync($service);$cdn=ServicePresentation::kind($service->product)==='cdn';
            return ['host_data'=>$this->host($service,true),'server_data'=>[],'custom_field_data'=>[],'module_button'=>$this->buttons($service),'module_client_area'=>false,'module_chart'=>false,'module_client_main_area'=>false,'module_power_status'=>!$cdn,'cloud_os'=>$this->operatingSystems($service),'cloud_os_group'=>[],'currency'=>$this->catalog->currency(),'config_options'=>[],'system_button'=>array_keys($this->buttons($service)),'clientarea_url'=>route('console.services.show',$service)];
        }
        if($get&&in_array($path,['/host/trafficusage','/dcim/traffic_usage'],true)){
            $s=$this->service($account,$request->input('host_id',$request->input('id')));
            return ['id'=>$s->id,'usage'=>data_get($s->specs,'traffic',[]),'available'=>is_array(data_get($s->specs,'traffic'))];
        }
        abort_unless($request->isMethod('POST'),405,'该接口需要 POST 请求。');
        if($path==='/host/renew'){$s=$this->service($account,$request->input('hostid',$request->input('id')));return $this->commerce->renew($request,$account,$s);}
        if($path==='/host/cancel'){
            $s=$this->service($account,$request->input('id',$request->input('hostid')));
            abort_unless(in_array($request->input('type','Endofbilling'),['Endofbilling','end_of_cycle'],true),422,'API 支持到期不续费，立即终止或退款请提交工单。');
            $s->update(['auto_renew'=>false]);return ['id'=>$s->id,'type'=>'Endofbilling','expires_at'=>$s->expires_at?->timestamp];
        }
        if($path==='/provision/default'||preg_match('#^/dcim/(on|off|reboot|reinstall|crack_pass|refresh_power_status)$#D',$path)){
            $s=$this->service($account,$request->input('id',$request->input('hostid')));
            $func=$path==='/provision/default'?$request->input('func'):basename($path);
            abort_if(ServicePresentation::kind($s->product)==='cdn',422,'CDN 不支持服务器电源管理。');
            if(in_array($func,['status','refresh_power_status'],true)){return ['status'=>$s->status==='active'?'on':($s->status==='stopped'?'off':'unknown'),'host_data'=>$this->host($s)];}
            abort_unless(in_array($s->status,['active','stopped'],true)&&!data_get($s->specs,'legacy_import.operation_hold',false),422,'当前实例状态不允许管理操作。');
            abort_if(ServicePresentation::kind($s->product)==='cdn',422,'CDN 不支持服务器电源或操作系统管理。');
            $action=match($func){'on','start'=>'start','off','stop'=>'stop','reboot'=>'reboot','reinstall'=>'reinstall','crack_pass'=>'password',default=>null};
            abort_unless($action && isset($this->buttons($s)[(string)$func]),422,'该产品暂不支持此操作。');
            if($action==='reinstall'){
                $allowed=array_map(fn($os)=>(string)$os['id'],$this->operatingSystems($s));
                abort_unless(in_array((string)$request->input('os'),$allowed,true),422,'所选系统不属于该产品允许列表。');
            }
            $sub=$this->commerce->request($request,$account,['action'=>$action,'os'=>$request->input('os'),'password'=>$request->input('password',$request->input('new_password')),'password_confirmation'=>$request->input('password',$request->input('new_password')),'force_shutdown'=>$request->input('force_shutdown',$request->input('force',false))]);
            if($request->hasSession()){$request->session()->forget('errors');}
            $response=app()->call([app(ServiceController::class),$action==='password'?'changePassword':'action'],['request'=>$sub,'service'=>$s]);
            $errors=$request->hasSession()?$request->session()->get('errors'):null;
            abort_if($errors&&$errors->any(),422,'操作失败，请在控制台核对实例状态或提交工单。');
            return ['id'=>$s->id,'action'=>$func,'submitted'=>true];
        }
        abort(422,'当前产品或兼容版本未提供此接口，请参考 API 文档中的支持清单。');
    }
    public function buttons(Service $service): array
    {
        if(in_array(ServicePresentation::kind($service->product),['cdn','colocation'],true)){return [];}
        $buttons=['on'=>'开机','off'=>'关机','reboot'=>'重启','reinstall'=>'重装系统'];
        if($service->product?->upstream_source!=='pterodactyl'){$buttons['crack_pass']='重置密码';}
        return $buttons;
    }
    private function operatingSystems(Service $service): array
    {
        if(in_array(ServicePresentation::kind($service->product),['cdn','colocation'],true)){return [];}
        if($service->product?->upstream_source==='mofang'){
            return (array)data_get($service->specs,'reseller_api.cloud_os',[]);
        }
        return collect(\App\Support\ProductOptions::options($service->product)['ostype']??[])->map(fn($row)=>['id'=>$row['value'],'name'=>$row['label']])->all();
    }
    private function sync(Service $service): void
    {
        if(!$service->external_ref || data_get($service->specs,'legacy_import.operation_hold',false)||!in_array($service->status,['active','stopped','provisioning'],true)){return;}
        $key='reseller:service-sync:'.$service->id;
        if(!\Illuminate\Support\Facades\Cache::add($key,true,60)){return;}
        try {
            if($service->product?->upstream_source==='qzvps'){
                app(\App\Services\Qzvps\QzvpsServiceSync::class)->sync($service);
            }elseif($service->product?->upstream_source==='mofang'&&str_starts_with($service->external_ref,'mofang:')){
                $detail=app(\App\Services\Mofang\Contracts\MofangGateway::class)->fetchService(substr($service->external_ref,7));
                app(\App\Services\Mofang\MofangServiceSync::class)->sync($service,$detail);
                $specs=$service->specs;$specs['reseller_api']['cloud_os']=array_values(array_map(fn($os)=>array_intersect_key($os,array_flip(['id','name','os_name','group_id'])),array_filter((array)data_get($detail,'data.cloud_os',[]),'is_array')));
                $service->update(['specs'=>$specs]);
            }
        }catch(\Throwable $e){report($e);}
    }

}
