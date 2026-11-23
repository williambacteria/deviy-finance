<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function reserve(Order $order, Product $product, int $quantity): ?StockReservation
    {
        if ($product->upstream_source === 'mofang') {
            return null;
        }

        $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

        if ($locked->stock < $quantity) {
            throw ValidationException::withMessages([
                'availability' => '库存不足，剩余 '.$locked->stock.' 台。',
            ]);
        }

        $locked->decrement('stock', $quantity);

        return StockReservation::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'status' => 'reserved',
            'expires_at' => now()->addDays(3),
        ]);
    }

    public function consume(Order $order): void
    {
        StockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', 'reserved')
            ->update(['status' => 'consumed']);
    }

    public function releaseExpired(): int
    {
        $released = 0;

        StockReservation::query()
            ->where('status', 'reserved')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($reservations) use (&$released): void {
                foreach ($reservations as $reservation) {

                    $released += \Illuminate\Support\Facades\DB::transaction(function () use ($reservation): int {
                        $order=Order::whereKey($reservation->order_id)->lockForUpdate()->first();
                        $locked=StockReservation::whereKey($reservation->id)->lockForUpdate()->first();
                        if(!$locked || $locked->status!=='reserved' || $locked->expires_at->isFuture()){return 0;}
                        if($order && $order->status==='paid'){$locked->update(['status'=>'consumed']);return 0;}
                        Product::whereKey($locked->product_id)->increment('stock',$locked->quantity);
                        $locked->update(['status'=>'released']);
                        if($order && $order->status==='pending'){$order->update(['status'=>'cancelled']);}
                        return 1;
                    });

                }
            });

        return $released;
    }
}
