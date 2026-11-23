<?php
namespace App\Http\Controllers\Console;
use App\Http\Controllers\Controller;
use App\Models\ResellerAccount;
use App\Services\Reseller\ResellerAuth;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ResellerController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Response
    {
        $account=ResellerAccount::where('user_id',$request->user()->id)->first();
        $logs=$account ? DB::table('reseller_api_logs')->where('reseller_account_id',$account->id)->latest('id')->limit(15)->get() : collect();
        return response()->view('console.reseller.index',compact('account','logs'))->header('Cache-Control','no-store');
    }
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isActive() && $request->user()->hasVerifiedRealName(),403,'请先完成实名认证。');
        $data=$request->validate(['site_name'=>['required','string','max:100'],'site_url'=>['required','url:https','max:255'],'purpose'=>['required','string','min:10','max:1000']]);
        DB::transaction(function()use($request,$data):void {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $account=ResellerAccount::where('user_id',$request->user()->id)->first();
            if($account && $account->status!=='rejected'){
                throw ValidationException::withMessages(['application'=>'已有申请或代理账户，请勿重复提交。']);
            }
            ResellerAccount::updateOrCreate(['user_id'=>$request->user()->id],$data+['status'=>'pending','review_note'=>null,'reviewed_by'=>null,'reviewed_at'=>null]);
        });
        return back()->with('status','API 申请已提交，请等待管理员审核。');
    }
    public function update(Request $request, ResellerAuth $auth): RedirectResponse
    {
        $account=ResellerAccount::where('user_id',$request->user()->id)->where('status','approved')->firstOrFail();
        $data=$request->validate(['action'=>['required','in:rotate,whitelist'],'current_password'=>['required','current_password:web'],'ip_allowlist'=>['nullable','string','max:1000']]);
        if($data['action']==='rotate'){
            return back()->with('api_secret',$auth->rotate($account))->with('status','新密钥仅显示本次，旧密钥和所有 Token 已立即失效。');
        }
        $ips=preg_split('/[\s,;]+/',trim((string)($data['ip_allowlist']??'')),-1,PREG_SPLIT_NO_EMPTY);
        if(count($ips)>20 || array_filter($ips,fn($ip)=>!filter_var($ip,FILTER_VALIDATE_IP))){
            throw ValidationException::withMessages(['ip_allowlist'=>'最多填写20个有效 IPv4/IPv6 地址，每行一个。']);
        }
        $account->update(['ip_allowlist'=>array_values(array_unique($ips))]);
        return back()->with('status','IP 白名单已更新。');
    }
    public function docs(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $markdown=file_get_contents(resource_path('views/console/reseller/api.md'));
        if($request->boolean('download')){
            return response($markdown)->header('Content-Type','text/markdown; charset=UTF-8')->header('Content-Disposition','attachment; filename="deviy-reseller-api.md"');
        }
        return response()->view('console.reseller.docs',['markdown'=>$markdown]);
    }
}
