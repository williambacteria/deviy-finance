<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\OrderCashier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderCashierController extends Controller
{
    public function store(Request $request,Order $order,OrderCashier $cashier): JsonResponse
    {
        abort_unless((int)$order->user_id===(int)$request->user()->id,404);
        $data=$request->validate(['gateway'=>['required','in:alipay,wechat,balance']]);
        try{return response()->json($cashier->start($request->user(),$order,$data['gateway']))->header('Cache-Control','no-store');}
        catch(\RuntimeException $e){report($e);return response()->json(['message'=>'付款状态暂未确认，请稍后重试；若已扣款请勿重复支付。'],503);}
    }

    public function show(Request $request,Order $order,OrderCashier $cashier): JsonResponse
    {
        abort_unless((int)$order->user_id===(int)$request->user()->id,404);
        $cashier->reconcile($order);
        return response()->json($cashier->state($order->refresh()))->header('Cache-Control','no-store');
    }

    public function destroy(Request $request,Order $order,OrderCashier $cashier): JsonResponse
    {
        abort_unless((int)$order->user_id===(int)$request->user()->id,404);
        try{return response()->json($cashier->close($request->user(),$order))->header('Cache-Control','no-store');}
        catch(\RuntimeException $e){report($e);return response()->json(['message'=>'原付款尚未确认关闭，请稍后再试。'],503);}
    }
}
