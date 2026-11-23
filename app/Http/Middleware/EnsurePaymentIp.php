<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaymentIp
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provider = str_contains($request->path(), 'wechat') ? 'wechat' : 'alipay';
        $allowed = (array) config('payments.'.$provider.'.allowed_ips', []);
        if ($allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            abort(403, '支付回调来源 IP 不在白名单。');
        }

        return $next($request);
    }
}
