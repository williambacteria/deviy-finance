<?php
namespace App\Services\Reseller;
use App\Models\ResellerAccount;
use App\Models\ResellerToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
class ResellerAuth
{
    public function check(ResellerAccount $account, Request $request): void
    {
        abort_unless($account->status === 'approved' && $account->user?->isActive() && $account->user->hasVerifiedEmail() && $account->user->hasVerifiedRealName(),403,'代理权限未开通、已停用或账户认证未完成。');
        abort_if($account->ip_allowlist && ! in_array($request->ip(),$account->ip_allowlist,true),403,'请求 IP 不在白名单中。');
    }
    public function credentials(Request $request, string $username, string $secret): ResellerAccount
    {
        $key='reseller-login:'.hash('sha256',mb_strtolower($username).'|'.$request->ip());
        abort_if(RateLimiter::tooManyAttempts($key,10),429,'登录过于频繁，请一分钟后重试。');
        $account=ResellerAccount::whereHas('user',fn($q)=>$q->where('email',$username))->with('user')->first();
        if (! $account || ! $account->secret_hash || ! hash_equals($account->secret_hash,hash('sha256',$secret))) {
            RateLimiter::hit($key,60); throw new HttpException(401,'API 账号或密钥错误。');
        }
        $this->check($account,$request); RateLimiter::clear($key);
        return $account;
    }
    public function authenticate(Request $request): ResellerAccount
    {
        if ($request->getUser() !== null) {
            return $this->credentials($request,(string)$request->getUser(),(string)$request->getPassword());
        }
        $plain=(string)$request->bearerToken();
        abort_unless(str_starts_with($plain,'dvy_'),401,'请先获取 API Token。');
        $token=ResellerToken::with('account.user')->where('token_hash',hash('sha256',$plain))->where('expires_at','>',now())->first();
        abort_unless($token && $token->account,401,'Token 无效或已过期，请重新登录。');
        $this->check($token->account,$request);
        return $token->account;
    }
    public function issue(ResellerAccount $account): string
    {
        $plain='dvy_'.Str::random(64);
        DB::transaction(function()use($account,$plain):void {
            ResellerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            ResellerToken::where('reseller_account_id',$account->id)->where('expires_at','<=',now())->delete();
            $keep=ResellerToken::where('reseller_account_id',$account->id)->latest('id')->limit(4)->pluck('id');
            ResellerToken::where('reseller_account_id',$account->id)->whereNotIn('id',$keep)->delete();
            ResellerToken::create(['reseller_account_id'=>$account->id,'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addHours(2)]);
        });
        return $plain;
    }
    public function rotate(ResellerAccount $account): string
    {
        $plain='dvyk_'.Str::random(48);
        DB::transaction(function()use($account,$plain):void {
            $account=ResellerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            abort_unless($account->status==='approved',403);
            $account->update(['secret_hash'=>hash('sha256',$plain),'secret_hint'=>substr($plain,-6)]);
            ResellerToken::where('reseller_account_id',$account->id)->delete();
        });
        return $plain;
    }
}
