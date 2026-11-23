<?php
namespace App\Services\Reseller;
use App\Models\Product;
use App\Support\Billing;
use App\Support\ProductOptions;
use App\Support\ServicePresentation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
class ResellerCatalog
{
    public function products(): Collection
    {
        return Product::catalogVisible()->where('status','active')->where('type','!=','colocation')->with(['prices'=>fn($q)=>$q->where('is_active',true),'group.parent'])->orderBy('sort_order')->orderBy('id')->get();
    }
    public function product(int $id): Product
    {
        return Product::catalogVisible()->where('status','active')->where('type','!=','colocation')->with(['prices'=>fn($q)=>$q->where('is_active',true),'group.parent'])->findOrFail($id);
    }
    public function currency(): array
    {
        return ['id'=>1,'code'=>'CNY','prefix'=>'¥','suffix'=>'元','format'=>1,'rate'=>'1.00000','default'=>1];
    }
    public function prices(Product $product, ?float $monthlyAddon = null): array
    {
        $row=['currency'=>1,'code'=>'CNY','type'=>'product','relid'=>$product->id];
        foreach(['onetime','hour','day','ontrial','monthly','quarterly','semiannually','annually','biennially','triennially']as $cycle){$row[$cycle]='-1.00';}
        foreach(['m','q','s','a','b','t','o']as $prefix){$row[$prefix.'setupfee']='0.00';}
        foreach($product->prices as $price){
            $row[$price->billing_cycle]=$monthlyAddon===null ? number_format((float)$price->price,2,'.','') : number_format($monthlyAddon*Billing::months($price->billing_cycle)*(ProductOptions::cycleDiscounts($product)[$price->billing_cycle]??1),2,'.','');
        }
        return [$row];
    }
    public function info(Product $product): array
    {
        $type=ServicePresentation::kind($product);
        return ['id'=>$product->id,'name'=>$product->name,'gid'=>$product->product_group_id,'group_name'=>$product->group?->name,'type'=>match($type){'cdn'=>'cdn','bare_metal'=>'dcim',default=>'cloud'},'description'=>e($product->short_description??''),'location_version'=>$product->updated_at?->timestamp??1,'stock_control'=>1,'qty'=>max(0,$product->stock),'server_type'=>'','price'=>$product->prices->first()?->price,'prices'=>$product->prices->pluck('price','billing_cycle')->all(),'region'=>$product->region,'hidden'=>0,'retired'=>0,'allow_qty'=>1,'auto_setup'=>'payment','pay_method'=>'prepayment','pay_type'=>json_encode(['pay_type'=>'recurring']),'is_truename'=>1,'is_domain'=>0,'tax'=>0,'host'=>json_encode(['show'=>0,'modify'=>0]),'password'=>json_encode(['show'=>0,'modify'=>0])];
    }
    public function definitions(Product $product): array
    {
        $result=[];$n=0;$kind=ServicePresentation::kind($product);
        if($product->upstream_source==='mofang'){
            foreach((array)data_get($product->metadata,'upstream_config_groups',[])as $group){
                $id=1000000+$product->id*100+(++$n);
                $rows=[];foreach((array)($group['options']??[])as $i=>$row){$rows[]=['id'=>$id*1000+$i+1,'value'=>(string)($row['value']??''),'label'=>(string)($row['label']??$row['value']??''),'price'=>(float)($row['price']??0)*(1+(float)data_get($product->metadata,'markup_percent',0)/100)];}
                if($rows){$result[$id]=['label'=>(string)($group['label']??$group['name']??'套餐配置'),'upstream'=>(string)$group['id'],'rows'=>$rows];}
            }
        }
        if($kind!=='cdn'){
            foreach(ProductOptions::options($product)as $key=>$rows){
                if($rows===[] || ($product->upstream_source==='mofang' && $key!=='ostype')){continue;}
                if($product->upstream_source==='mofang' && $key==='ostype'){continue;}
                $id=1000000+$product->id*100+(++$n);$range=ProductOptions::settings($product)[$key]['range'];
                $sub=[];foreach($rows as $i=>$row){$sub[]=$row+['id'=>$id*1000+$i+1];}
                $result[$id]=['label'=>ProductOptions::DIMENSIONS[$key]['label'],'key'=>$key,'rows'=>$sub,'range'=>$range];
            }
        }
        return $result;
    }
    public function config(Product $product): array
    {
        $options=[];
        foreach($this->definitions($product)as $id=>$definition){
            $range=$definition['range']??['enabled'=>false];$rows=$definition['rows'];
            $option=['id'=>$id,'gid'=>$product->id,'option_name'=>$definition['label'],'option_type'=>$range['enabled']?14:1,'qty_minimum'=>0,'qty_maximum'=>$range['enabled']?(int)(($range['max']-$range['min'])/$range['step']):0,'qty_stage'=>1,'hidden'=>0,'upgrade'=>0,'order'=>count($options),'sub'=>[]];
            if($range['enabled']){$rows=[['id'=>$id*1000+1,'label'=>'额外配置（基础 '.$range['min'].'，每份 '.$range['step'].'）','price'=>$range['unit_price']*$range['step']]];}
            foreach($rows as $i=>$row){$option['sub'][]=['id'=>$row['id'],'config_id'=>$id,'option_name'=>$row['label'],'sort_order'=>$i,'hidden'=>0,'qty_minimum'=>0,'qty_maximum'=>$option['qty_maximum'],'pricings'=>$this->prices($product,(float)$row['price'])];}
            $options[]=$option;
        }
        return ['flag'=>false,'products'=>$this->info($product),'customfields'=>[],'product_pricings'=>$this->prices($product),'advanced'=>[],'config_groups'=>[['id'=>$product->id,'name'=>$product->name.'配置','description'=>'','options'=>$options]],'config_links'=>[$product->id]];
    }
    public function selection(Product $product, array $input): array
    {
        $options=[];$upstream=[];$definitions=$this->definitions($product);
        foreach($input as $id=>$value){
            $definition=$definitions[$id]??null;
            if(!$definition){throw ValidationException::withMessages(['configoption'=>'配置项无效，请重新同步商品配置。']);}
            if(data_get($definition,'range.enabled')){
                $range=$definition['range'];
                if(!ctype_digit((string)$value)|| (int)$value>(int)(($range['max']-$range['min'])/$range['step'])){throw ValidationException::withMessages(['configoption'=>'额外配置数量超出允许范围。']);}
                $options[$definition['key']]=(string)($range['min']+(int)$value*$range['step']);continue;
            }
            $row=collect($definition['rows'])->first(fn($r)=>(string)$r['id']===(string)$value);
            if(!$row){throw ValidationException::withMessages(['configoption'=>'配置选项已变更，请重新同步。']);}
            if(isset($definition['upstream'])){$upstream[$definition['upstream']]=(string)$row['value'];}else{$options[$definition['key']]=(string)$row['value'];}
        }
        foreach($definitions as $id=>$definition){if(isset($definition['upstream'])&&!array_key_exists($definition['upstream'],$upstream)){$upstream[$definition['upstream']]=(string)$definition['rows'][0]['value'];}}
        try {ProductOptions::resolve($product,$options);}catch(\InvalidArgumentException $e){throw ValidationException::withMessages(['configoption'=>$e->getMessage()]);}
        return ['options'=>$options,'upstream_config'=>$upstream];
    }
}
