<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Legacy\LegacyFinanceBridge;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LegacyFinanceController extends Controller
{
    public function __invoke(Request $request, LegacyFinanceBridge $bridge): Response
    {
        $protocol=app(\App\Services\Reseller\ResellerProtocol::class);
        return $protocol->accepts($request) ? $protocol->handle($request) : $bridge->handle($request);
    }
}
