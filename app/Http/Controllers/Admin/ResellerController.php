<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\ResellerAccount;
use App\Models\ResellerToken;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
class ResellerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user('admin')->isSuperAdmin(),403);
        $status=$request->validate(['status'=>['nullable','in:pending,approved,rejected,suspended']])['status']??null;
        $accounts=ResellerAccount::with('user')->when($status,fn($q)=>$q->where('status',$status))->latest('id')->paginate(20)->withQueryString();
        $counts=ResellerAccount::selectRaw('status, count(*) total')->groupBy('status')->pluck('total','status');
        return view('admin.resellers.index',compact('accounts','counts','status'));
    }
    public function update(Request $request, ResellerAccount $reseller, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuperAdmin(),403);
        $data=$request->validate(['status'=>['required','in:approved,rejected,suspended'],'review_note'=>['nullable','string','max:1000']]);
        DB::transaction(function()use($request,$reseller,$data,$audit):void {
            $reseller=ResellerAccount::with('user')->whereKey($reseller->id)->lockForUpdate()->firstOrFail();
            if($data['status']==='approved'){
                abort_unless($reseller->user?->isActive() && $reseller->user->hasVerifiedEmail() && $reseller->user->hasVerifiedRealName(),422,'客户需完成邮箱及实名认证且账户正常。');
            }
            $reseller->update($data+['reviewed_by'=>$request->user('admin')->id,'reviewed_at'=>now()]);
            if($data['status']!=='approved'){
                ResellerToken::where('reseller_account_id',$reseller->id)->delete();
                $reseller->update(['secret_hash'=>null,'secret_hint'=>null]);
            }
            $audit->record($request->user('admin'),'reseller.reviewed','代理 API 审核：'.$data['status'],$reseller,['user_id'=>$reseller->user_id]);
        });
        return back()->with('status','代理 API 权限已更新；通过后客户可自行生成密钥。');
    }
}
