<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Support\Colocation;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
class ColocationServiceController extends Controller
{
    public function update(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        abort_unless(Colocation::service($service),404);
        abort_unless(in_array($request->user('admin')->role,['super_admin','support'],true),403);
        $data=$request->validate(['name'=>['required','string','max:190'],'ip_address'=>['nullable','ip'],'expires_at'=>['required','date_format:Y-m-d'],'rack_units'=>['required','in:1,2,4,tower'],'cpu_model'=>['required','string','max:120'],'memory_gb'=>['required','integer','min:1','max:65536'],'gpu_model'=>['nullable','string','max:120'],'datacenter'=>['required','string','max:190'],'upload_mbps'=>['required','integer','min:0','max:100000'],'download_mbps'=>['required','integer','min:0','max:100000'],'status'=>['required','in:active,pending,terminated']]);
        DB::transaction(function()use($data,$request,$service,$audit):void {
            $service=Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $rackUnits = $data['rack_units'] === 'tower' ? null : (int) $data['rack_units'];
            $specs = (array) $service->specs;
            $specs['colocation'] = array_merge((array) ($specs['colocation'] ?? []), [
                'rack_units' => $rackUnits,
                'form_factor' => $rackUnits ? $rackUnits.'U' : '塔式',
                'datacenter' => $data['datacenter'],
                'record_only' => true,
            ]);
            foreach(['cpu_model','memory_gb','gpu_model','upload_mbps','download_mbps']as $key){$specs[$key]=$data[$key]??null;}
            $expires=\Illuminate\Support\Carbon::createFromFormat('Y-m-d',$data['expires_at'],config('app.timezone'))->endOfDay();
            $service->update(['name'=>$data['name'],'ip_address'=>$data['ip_address']??null,'status'=>$data['status'],'specs'=>$specs,'expires_at'=>$expires,'next_due_at'=>$expires,'auto_renew'=>false,'node_id'=>null,'external_ref'=>null,'provision_status'=>'manual','bandwidth_mbps'=>$data['upload_mbps']]);
            $audit->record($request->user('admin'),'service.colocation.updated','更新托管登记：'.$service->service_no,$service,['expires_at'=>$expires->toIso8601String()]);
        });
        return back()->with('status','托管资料已更新。');
    }
}
