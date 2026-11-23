<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductPrice;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Models\UpstreamOrder;
use App\Support\Billing;
use App\Support\DocumentNumber;
use App\Support\ProductOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class RenewalService
{
    /** @return Collection<int, ProductPrice> */
    public function pricesFor(Service $service): Collection
    {
        $query = ProductPrice::query()->where('product_id', $service->product_id)->where('is_active', true);
        if (data_get($service->specs, 'admin_deletion')) {
            return $query->whereRaw('1 = 0')->get();
        }
        if (\App\Support\Colocation::service($service)) { return $query->whereRaw('1 = 0')->get(); }
        if (data_get($service->specs, 'legacy_import.source') === 'www.deviy.cn' && (data_get($service->specs, 'legacy_import.operation_hold', false) || ! in_array($service->status, ['active', 'suspended', 'stopped'], true))) {
            return $query->whereRaw('1 = 0')->get();
        }
        $contractPrices=data_get($service->specs,'contract_prices');
        if (is_array($contractPrices) && $contractPrices !== []) { $query->whereIn('billing_cycle',array_keys($contractPrices)); }
        $legacyPrices = data_get($service->specs, 'legacy_import.renewal_prices');
        if (data_get($service->specs, 'legacy_import.source') === 'www.deviy.cn' && is_array($legacyPrices)) {
            $query->whereIn('billing_cycle', array_keys($legacyPrices));
        }

        return $query->orderBy('price')->get();
    }

    /**
     * @return array{price: ProductPrice, amount: string}
     */
    public function quote(Service $service, string $cycle): array
    {
        if (data_get($service->specs, 'admin_deletion')) {
            throw ValidationException::withMessages(['service' => '该服务正在核对销毁结果，暂不可续费。']);
        }
        if (\App\Support\Colocation::service($service)) { throw ValidationException::withMessages(['service'=>'托管续费请联系机房确认。']); }
        $price = ProductPrice::query()
            ->where('product_id', $service->product_id)
            ->where('billing_cycle', $cycle)
            ->where('is_active', true)
            ->first();

        if (! $price) {
            throw ValidationException::withMessages(['billing_cycle' => '该周期暂不可续费，请选择其他周期。']);
        }

        if (data_get($service->specs, 'legacy_import.source') === 'www.deviy.cn' && (data_get($service->specs, 'legacy_import.operation_hold', false) || ! in_array($service->status, ['active', 'suspended', 'stopped'], true))) {
            throw ValidationException::withMessages(['service' => '该迁入实例处于历史或接口核查状态，暂不可续费，请联系客服。']);
        }

        $legacyPrices = data_get($service->specs, 'legacy_import.renewal_prices');
        if (data_get($service->specs, 'legacy_import.source') === 'www.deviy.cn' && is_array($legacyPrices)) {
            $amount = $legacyPrices[$cycle] ?? null;
            if (! is_string($amount) || ! preg_match('/^\d+(?:\.\d{1,6})?$/D', $amount)) {
                throw ValidationException::withMessages(['billing_cycle' => '迁入产品暂按旧站原周期续费，变更周期请联系客服确认。']);
            }

            return ['price' => $price, 'amount' => number_format((float) $amount, 2, '.', '')];
        }

        $contractPrices=data_get($service->specs,'contract_prices');
        if (is_array($contractPrices) && ! array_key_exists($cycle,$contractPrices)) { throw ValidationException::withMessages(['billing_cycle'=>'该实例仅支持合同约定的续费周期。']); }
        $contract = data_get($service->specs,'contract_prices.'.$cycle);
        if (is_numeric($contract) && (float)$contract>=0) { return ['price'=>$price,'amount'=>number_format((float)$contract,2,'.','')]; }
        $renewalOverride = (float) data_get($service->specs, 'renewal_amount', 0);
        if ($renewalOverride > 0) {
            $months = Billing::months($cycle);
            $discount = (float) (ProductOptions::cycleDiscounts($service->product)[$cycle] ?? 1);

            return [
                'price' => $price,
                'amount' => number_format(round($renewalOverride * $months * $discount, 2), 2, '.', ''),
            ];
        }

        $product = $service->product;
        $monthlyAddon = 0.0;
        if ($product) {
            $specs = is_array($service->specs) ? $service->specs : [];
            $input = [];
            foreach (ProductOptions::DIMENSIONS as $key => $definition) {
                if ($key === 'ostype') {
                    $input[$key] = data_get($product->metadata, 'controller.ostype') ?: $service->os;
                } elseif (array_key_exists($key, $specs)) {
                    $input[$key] = $specs[$key];
                }
            }

            try {
                $monthlyAddon = (float) ProductOptions::resolve($product, $input)['monthly_addon'];
            } catch (\InvalidArgumentException) {
                $monthlyAddon = 0.0;
            }
        }

        $months = Billing::months($cycle);
        $discount = $product ? (float) (ProductOptions::cycleDiscounts($product)[$cycle] ?? 1) : 1.0;
        $amount = round((float) $price->price + $monthlyAddon * $months * $discount, 2);

        return ['price' => $price, 'amount' => number_format($amount, 2, '.', '')];
    }

    public function createOrder(Service $service, string $cycle): Order
    {
        if (\App\Support\Colocation::service($service)) { throw ValidationException::withMessages(['service'=>'托管续费请联系机房确认。']); }
        $existing = Order::query()
            ->where('type', 'renewal')
            ->where('status', 'pending')
            ->whereHas('items', fn ($query) => $query->where('service_id', $service->id)->where('billing_cycle', $cycle))
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $quote = $this->quote($service, $cycle);
        $amount = $quote['amount'];

        return DB::transaction(function () use ($service, $cycle, $amount): Order {
            $order = Order::create([
                'order_no' => DocumentNumber::make('ORD'),
                'user_id' => $service->user_id,
                'status' => 'pending',
                'type' => 'renewal',
                'subtotal' => $amount,
                'discount' => 0,
                'tax' => 0,
                'total' => $amount,
                'currency' => 'CNY',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $service->product_id,
                'service_id' => $service->id,
                'product_name' => $service->name.' · 续费',
                'billing_cycle' => $cycle,
                'unit_price' => $amount,
                'quantity' => 1,
                'amount' => $amount,
            ]);

            Invoice::create([
                'invoice_no' => DocumentNumber::make('INV'),
                'user_id' => $service->user_id,
                'order_id' => $order->id,
                'status' => 'unpaid',
                'amount' => $amount,
                'paid_amount' => 0,
                'due_at' => now()->addDays(3),
                'items' => [['name' => $service->name.' 续费', 'amount' => (float) $amount]],
            ]);

            return $order;
        });
    }

    public function apply(Order $order): void
    {
        if ($order->type !== 'renewal') {
            return;
        }

        foreach ($order->items as $item) {
            if (! $item->service_id) {
                continue;
            }

            $service = Service::query()->lockForUpdate()->find($item->service_id);
            if (! $service) {
                continue;
            }

            if ($service->product?->upstream_source === 'mofang') {
                $upstreamServiceId = UpstreamOrder::query()
                    ->where('service_id', $service->id)
                    ->whereNotNull('upstream_service_id')
                    ->latest('id')
                    ->value('upstream_service_id');

                if (! $upstreamServiceId && str_starts_with((string)$service->external_ref,'mofang:')) { $upstreamServiceId=substr($service->external_ref,7); }
                if (! $upstreamServiceId) {
                    throw ValidationException::withMessages(['renewal' => '实例 ID 缺失，无法续费。']);
                }

                UpstreamOrder::create([
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'product_id' => $service->product_id,
                    'source' => 'mofang',
                    'type' => 'renewal',
                    'status' => 'pending',
                    'amount' => $item->amount,
                    'billing_cycle' => $item->billing_cycle,
                    'upstream_service_id' => $upstreamServiceId,
                    'payload' => [
                        'billing_cycle' => $item->billing_cycle,
                        'months' => Billing::months($item->billing_cycle),
                        'service_no' => $service->service_no,
                        'order_no' => $order->order_no,
                    ],
                ]);

                $service->update(['provision_status' => 'upstream_renewal_queued']);

                continue;
            }

            $months = Billing::months($item->billing_cycle);
            $base = $service->expires_at && $service->expires_at->isFuture() ? $service->expires_at : now();
            $expires = $base->copy()->addMonths($months);
            $wasSuspended = in_array($service->status, ['suspended', 'stopped'], true);

            $service->update([
                'expires_at' => $expires,
                'next_due_at' => $expires->copy()->subDays(7),
                'last_renewed_at' => now(),
                'grace_ends_at' => null,
                'status' => $wasSuspended ? 'active' : $service->status,
                'provision_status' => $wasSuspended ? 'queued' : $service->provision_status,
            ]);

            $provider = $service->product?->upstream_source;
            if ($provider === 'qzvps' && data_get($service->specs, 'legacy_import.source') === 'www.deviy.cn') {
                ProvisionTask::create([
                    'service_id' => $service->id,
                    'operation' => 'renew',
                    'status' => 'queued',
                    'payload' => ['source' => 'renewal', 'order_no' => $order->order_no],
                    'attempts' => 0,
                ]);
            }

            if ($wasSuspended && ($service->node_id || in_array($provider, ['qzvps', 'pterodactyl'], true)) && $provider !== 'mofang') {
                ProvisionTask::create([
                    'service_id' => $service->id,
                    'operation' => 'unsuspend',
                    'status' => 'queued',
                    'payload' => ['source' => 'renewal', 'order_no' => $order->order_no],
                    'attempts' => 0,
                ]);
            }
        }
    }

    public function nextDueLabel(Service $service): string
    {
        if (! $service->next_due_at) {
            return '—';
        }

        return $service->next_due_at->isPast()
            ? '已到期'
            : $service->next_due_at->format('Y-m-d').'（'.max(0, (int) now()->diffInDays($service->next_due_at, false)).' 天）';
    }
}
