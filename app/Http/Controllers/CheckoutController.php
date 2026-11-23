<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Commerce\AvailabilityService;
use App\Services\Commerce\InventoryService;
use App\Support\Billing;
use App\Support\DocumentNumber;
use App\Support\ProductOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function show(Request $request, Product $product, AvailabilityService $availability): View|RedirectResponse
    {
        if (! $request->user()->hasVerifiedRealName()) {
            return redirect()->route('console.real-name')->with('status', '购买商品前请先完成实名认证。');
        }

        abort_unless($product->isPurchasable(), 404);

        $product->load([
            'group',
            'prices' => fn ($query) => $query->where('is_active', true)->orderBy('price'),
        ]);

        $optionRows = ProductOptions::options($product);
        try {
            $selected = ProductOptions::resolve($product, $request->input('options', []));
        } catch (InvalidArgumentException) {
            $selected = ProductOptions::resolve($product, []);
        }

        $selectedCycle = (string) $request->query('cycle', $request->query('billing_cycle', $product->prices->first()?->billing_cycle ?? 'monthly'));
        if (! $product->prices->contains('billing_cycle', $selectedCycle)) {
            $selectedCycle = $product->prices->first()?->billing_cycle ?? 'monthly';
        }
        $selectedQuantity = max(1, min(10, (int) $request->query('quantity', 1)));
        $upstreamConfig = is_array($request->query('upstream_config')) ? $request->query('upstream_config') : [];
        $upstreamMonthlyAddon = $this->upstreamMonthlyAddon($product, $upstreamConfig);

        return view('checkout.show', [
            'product' => $product,
            'cycles' => Billing::cycles(),
            'availability' => $availability->check($product, $selectedQuantity),
            'optionRows' => $optionRows,
            'selectedOptions' => $selected['selection'],
            'monthlyAddon' => $selected['monthly_addon'] + $upstreamMonthlyAddon,
            'selectedCycle' => $selectedCycle,
            'selectedQuantity' => $selectedQuantity,
            'cycleDiscounts' => ProductOptions::cycleDiscounts($product),
        ]);
    }

    public function store(Request $request, Product $product, AvailabilityService $availability, InventoryService $inventory): RedirectResponse
    {
        if (! $request->user()->hasVerifiedRealName()) {
            throw ValidationException::withMessages(['real_name' => '购买商品前请先完成实名认证。']);
        }

        abort_unless($product->isPurchasable(), 404);

        $data = $request->validate([
            'billing_cycle' => ['required', 'string', 'in:monthly,quarterly,semiannually,annually'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'region' => ['nullable', 'string', 'max:64'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:80'],
            'upstream_os' => ['nullable', 'string', 'max:190'],
            'upstream_config' => ['nullable', 'array'],
            'upstream_config.*' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $configuration = ProductOptions::resolve($product, is_array($data['options'] ?? null) ? $data['options'] : []);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['options' => $exception->getMessage()]);
        }

        $configuration['upstream_os'] = $data['upstream_os'] ?? null;
        $configuration['upstream_config'] = $data['upstream_config'] ?? [];
        $upstreamAddon = $this->upstreamMonthlyAddon($product, $configuration['upstream_config']);
        $configuration['upstream_addon'] = $upstreamAddon;

        $price = ProductPrice::query()
            ->whereBelongsTo($product)
            ->where('billing_cycle', $data['billing_cycle'])
            ->where('is_active', true)
            ->first();

        if (! $price) {
            throw ValidationException::withMessages([
                'billing_cycle' => '该周期暂不可购买，请选择其他周期。',
            ]);
        }

        $months = Billing::months($data['billing_cycle']);
        $discount = ProductOptions::cycleDiscounts($product)[$data['billing_cycle']] ?? 1;
        $addon = bcmul((string) ($configuration['monthly_addon'] + $upstreamAddon), (string) ($months * $discount), 6);
        $unitPrice = bcadd((string) $price->price, $addon, 6);
        $amount = bcmul($unitPrice, (string) $data['quantity'], 6);

        $availabilityResult = $availability->check($product, (int) $data['quantity'], $data['billing_cycle']);
        if ($availabilityResult['errors'] !== []) {
            throw ValidationException::withMessages([
                'availability' => implode(' ', $availabilityResult['errors']),
            ]);
        }

        $order = DB::transaction(function () use ($request, $product, $data, $amount, $unitPrice, $configuration, $inventory) {
            $order = Order::create([
                'order_no' => DocumentNumber::make('ORD'),
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'type' => 'purchase',
                'subtotal' => $amount,
                'discount' => 0,
                'tax' => 0,
                'total' => $amount,
                'currency' => 'CNY',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'billing_cycle' => $data['billing_cycle'],
                'unit_price' => $unitPrice,
                'quantity' => $data['quantity'],
                'amount' => $amount,
                'configuration' => [
                    'region' => $data['region'] ?? $product->region,
                    'specs' => $configuration['specs'],
                    'ostype' => $configuration['ostype'],
                    'options' => $configuration['selection'],
                    'upstream_os' => $configuration['upstream_os'] ?? null,
                    'upstream_config' => $configuration['upstream_config'] ?? [],
                    'monthly_addon' => $configuration['monthly_addon'],
                ],
            ]);

            $inventory->reserve($order, $product, (int) $data['quantity']);

            Invoice::create([
                'invoice_no' => DocumentNumber::make('INV'),
                'user_id' => $request->user()->id,
                'order_id' => $order->id,
                'status' => 'unpaid',
                'amount' => $amount,
                'paid_amount' => 0,
                'due_at' => now()->addDays(3),
                'items' => [['name' => $product->name, 'amount' => (float) $amount]],
            ]);

            return $order;
        });

        return redirect()
            ->route('console.orders.show', ['order'=>$order, 'cashier'=>1])
            ->with('status', '订单已创建，请在 3 天内完成支付。');
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function upstreamMonthlyAddon(Product $product, array $configuration): float
    {
        if (data_get($product->metadata, 'upstream') !== 'mofang') {
            return 0.0;
        }

        $groups = data_get($product->metadata, 'upstream_config_groups', []);
        if (! is_array($groups)) {
            return 0.0;
        }

        $markup = 1 + ((float) data_get($product->metadata, 'markup_percent', 0) / 100);
        $addon = 0.0;

        foreach ($groups as $group) {
            $selected = (string) ($configuration[$group['id'] ?? ''] ?? '');
            if ($selected === '') {
                continue;
            }

            foreach (($group['options'] ?? []) as $option) {
                if ((string) ($option['value'] ?? '') === $selected) {
                    $addon += ((float) ($option['price'] ?? 0)) * $markup;
                    break;
                }
            }
        }

        return round($addon, 2);
    }
}
