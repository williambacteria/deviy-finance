<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\CloudNode;
use App\Models\LedgerEntry;
use App\Models\Notice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Models\UpstreamOrder;
use App\Services\Billing\RenewalService;
use App\Services\Commerce\AvailabilityService;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\IpAllocator;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\Billing;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\ProductOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;


class OrderPaymentService
{
    public function __construct(private RenewalService $renewals, private AvailabilityService $availability, private InventoryService $inventory, private IpAllocator $ipAllocator) {}

    public function assertPayable(User $user, Order $order): void
    {
        abort_unless((int) $order->user_id === (int) $user->id, 404);
        if ($order->type === 'legacy_history' || $order->status !== 'pending') {
            throw ValidationException::withMessages(['order' => '该订单已处理或仅为历史记录，当前不能支付。']);
        }
        if (! $user->isActive() || ! $user->hasVerifiedRealName()) {
            throw ValidationException::withMessages(['real_name' => '请先确认账户正常并完成实名认证。']);
        }
        if (! in_array($order->type, ['renewal', 'traffic'], true)) {
            foreach ($order->items as $item) {
                if (! $item->product) { throw ValidationException::withMessages(['order' => '商品不存在，请联系客服。']); }
                $product = clone $item->product;
                $product->stock += (int) \App\Models\StockReservation::where('order_id', $order->id)->where('product_id', $product->id)->where('status', 'reserved')->sum('quantity');
                $result = $this->availability->check($product, $item->quantity, $item->billing_cycle);
                if ($result['errors'] !== []) {
                    throw ValidationException::withMessages(['availability' => implode(' ', $result['errors'])]);
                }
            }
        }
    }

    public function balance(User $user, Order $order): Order
    {
        $this->assertPayable($user, $order);
        return $this->apply($user, $order);
    }

    public function capture(Payment $payment, string $transaction): Payment
    {
        abort_unless($payment->order_id !== null && in_array($payment->gateway, ['alipay','wechat'], true), 422);
        $this->apply($payment->user, $payment->order, $payment, $transaction);
        return $payment->refresh();
    }

    private function apply(User $customer, Order $order, ?Payment $external = null, ?string $transaction = null): Order
    {
        $renewals = $this->renewals;
        $ipAllocator = $this->ipAllocator;
        $fulfilled = DB::transaction(function () use ($customer, $order, $external, $transaction, $renewals, $ipAllocator): bool {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $lockedOrder->user_id === (int) $user->id, 404);
            if ($external) {
                $lockedPayment = Payment::whereKey($external->id)->lockForUpdate()->firstOrFail();
                if ($lockedPayment->status === 'succeeded') { return false; }
                if ($lockedPayment->order_id !== $lockedOrder->id || $lockedPayment->user_id !== $user->id || ! in_array($lockedPayment->status, ['pending','cancelled'], true) || Money::compare($lockedPayment->amount, $lockedOrder->total) !== 0 || $transaction === null || $transaction === '') {
                    throw new \RuntimeException('订单支付关联、金额或交易号不匹配。');
                }
                if (Payment::where('gateway', $lockedPayment->gateway)->where('transaction_id', $transaction)->whereKeyNot($lockedPayment->id)->exists()) {
                    throw new \RuntimeException('交易号已关联其他支付单。');
                }
                $lockedPayment->update(['status'=>'succeeded','transaction_id'=>$transaction,'paid_at'=>now()]);
                if ($lockedOrder->status !== 'pending' || $lockedOrder->type === 'legacy_history') {
                    $user->update(['balance'=>Money::add($user->balance,$lockedPayment->amount)]);
                    LedgerEntry::create(['user_id'=>$user->id,'type'=>'order_overpayment','amount'=>$lockedPayment->amount,'balance_after'=>$user->balance,'reference_type'=>Payment::class,'reference_id'=>$lockedPayment->id,'description'=>'订单已结束或已支付，本次收款转入账户余额 '.$lockedOrder->order_no]);
                    $lockedPayment->update(['payload'=>array_merge((array)$lockedPayment->payload,['settlement'=>'wallet_credit'])]);
                    return false;
                }
            } else {
                if ($lockedOrder->status !== 'pending' || $lockedOrder->type === 'legacy_history') {
                    throw ValidationException::withMessages(['order'=>'订单已支付，请勿重复操作。']);
                }
                if (Payment::where('order_id',$lockedOrder->id)->whereIn('gateway',['alipay','wechat'])->where('status','pending')->exists()) {
                    throw ValidationException::withMessages(['order'=>'当前有扫码付款正在进行，请先关闭原支付再使用余额。']);
                }
                if (Money::compare($user->balance,$lockedOrder->total)<0) {
                    throw ValidationException::withMessages(['balance'=>'账户余额不足，请选择支付宝或微信支付。']);
                }
                $user->update(['balance'=>Money::subtract($user->balance,$lockedOrder->total)]);
                $invoice=$lockedOrder->invoices()->first();
                Payment::create(['payment_no'=>DocumentNumber::make('PAY'),'user_id'=>$user->id,'invoice_id'=>$invoice?->id,'order_id'=>$lockedOrder->id,'gateway'=>'balance','amount'=>$lockedOrder->total,'status'=>'succeeded','transaction_id'=>'BAL-'.Str::uuid(),'paid_at'=>now(),'payload'=>['source'=>'order_cashier']]);
                LedgerEntry::create(['user_id'=>$user->id,'type'=>'payment','amount'=>'-'.$lockedOrder->total,'balance_after'=>$user->balance,'reference_type'=>Order::class,'reference_id'=>$lockedOrder->id,'description'=>'订单支付 '.$lockedOrder->order_no]);
            }
            $this->inventory->consume($lockedOrder);
            $lockedOrder->update(['status'=>'paid','paid_at'=>now()]);
            $lockedOrder->invoices()->update(['status'=>'paid','paid_amount'=>$lockedOrder->total,'paid_at'=>now()]);
            if ($lockedOrder->type === 'traffic') {
                app(\App\Services\Mofang\MofangTrafficOrders::class)->paid($lockedOrder);
                return true;
            }
            if ($lockedOrder->type === 'renewal') {
                $renewals->apply($lockedOrder);

                return true;
            }

            foreach ($lockedOrder->items as $item) {
                $product = $item->product;
                $months = Billing::months($item->billing_cycle);
                $metadata = $product?->metadata ?? [];
                $configuration = is_array($item->configuration ?? null) ? $item->configuration : [];
                $controller = $metadata['controller'] ?? [];
                $specs = array_merge([
                    'cpu' => 2,
                    'memory_gb' => 4,
                    'disk_gb' => 40,
                    'data_disk_gb' => 0,
                    'bandwidth_mbps' => 10,
                ], is_array($metadata['specs'] ?? null) ? $metadata['specs'] : [], is_array($configuration['specs'] ?? null) ? $configuration['specs'] : []);
                $controllerNodeId = (string) ($controller['node_id'] ?? '');
                $node = $controllerNodeId !== ''
                    ? CloudNode::query()->where('agent_node_id', $controllerNodeId)->first()
                    : null;
                $node ??= CloudNode::query()
                    ->where('status', 'online')
                    ->where('region', $product?->region)
                    ->first() ?? CloudNode::query()->where('status', 'online')->first();
                $expiresAt = now()->addMonths($months);
                $reminderDays = (int) ($metadata['expiry_reminder_days'] ?? 7);
                $selectedOs = (string) ($configuration['ostype'] ?? $controller['ostype'] ?? 'l26');
                $isQzvps = $product?->upstream_source === 'qzvps';
                $ostype = $isQzvps ? $selectedOs : ProductOptions::controllerOSType($selectedOs);
                $specs['ostype'] = $ostype;
                $os = $isQzvps
                    ? (string) (data_get($configuration, 'selection.ostype.label') ?: $selectedOs)
                    : (string) (data_get($configuration, 'selection.ostype.label') ?: (ProductOptions::OS_OPTIONS[$selectedOs] ?? 'Linux'));

                for ($i = 0; $i < $item->quantity; $i++) {
                    $isUpstream = $product?->upstream_source === 'mofang';
                    $isPterodactyl = $product?->upstream_source === 'pterodactyl';
                    $isWindowsOs = str_contains(strtolower($selectedOs), 'windows');
                    $remoteUsername = $isQzvps
                        ? ($isWindowsOs ? 'Administrator' : 'root')
                        : (str_starts_with($ostype, 'win') ? 'Administrator' : 'root');
                    $serviceSpecs = $specs;
                    $serviceSpecs['remote'] = [
                        'address' => '',
                        'port' => null,
                        'username' => $remoteUsername,
                        'password' => $isQzvps
                            ? Str::upper(Str::random(3)).Str::lower(Str::random(9)).random_int(100000, 999999)
                            : Str::password(16),
                        'protocol' => $isQzvps
                            ? ($isWindowsOs ? 'rdp' : 'ssh')
                            : (str_starts_with($ostype, 'win') ? 'rdp' : 'ssh'),
                        'note' => '',
                    ];
                    $service = Service::create([
                        'service_no' => DocumentNumber::make('SVC'),
                        'user_id' => $user->id,
                        'product_id' => $item->product_id,
                        'billing_cycle' => $item->billing_cycle,
                        'order_id' => $lockedOrder->id,
                        'node_id' => ($isUpstream || $isPterodactyl || $isQzvps) ? null : $node?->id,
                        'name' => $item->product_name.($item->quantity > 1 ? ' #'.($i + 1) : ''),
                        'status' => 'provisioning',
                        'region' => $product?->region ?? 'default',
                        'ip_address' => null,
                        'nat_ip' => null,
                        'nat_port_start' => null,
                        'nat_port_end' => null,
                        'bandwidth_mbps' => (int) ($specs['download_mbps'] ?? $specs['bandwidth_mbps'] ?? 10),
                        'os' => $os,
                        'specs' => $serviceSpecs,
                        'expires_at' => $expiresAt->copy(),
                        'next_due_at' => $expiresAt->copy()->subDays($reminderDays),
                        'provision_status' => $isPterodactyl ? 'pterodactyl_queued' : ($isUpstream ? 'upstream_queued' : 'queued'),
                    ]);

                    if ($isUpstream) {
                        UpstreamOrder::create([
                            'order_id' => $lockedOrder->id,
                            'service_id' => $service->id,
                            'product_id' => $product->id,
                            'source' => 'mofang',
                            'status' => 'pending',
                            'amount' => bcdiv((string) $item->amount, (string) max(1, $item->quantity), 6),
                            'payload' => [
                                'product_id' => $product->upstream_product_id,
                                'quantity' => 1,
                                'billing_cycle' => $item->billing_cycle,
                                'customer_email' => $user->email,
                                'service_no' => $service->service_no,
                                'order_no' => $lockedOrder->order_no,
                                'os' => data_get($configuration, 'upstream_os'),
                                'configoption' => data_get($configuration, 'upstream_config', []),
                            ],
                        ]);

                        continue;
                    }

                    if (! $isPterodactyl) {
                        $ipAllocator->allocate($node, $service, $product);
                        $service->refresh();
                    }

                    ProvisionTask::create([
                        'service_id' => $service->id,
                        'operation' => 'create',
                        'status' => 'queued',
                        'payload' => ['order_no' => $lockedOrder->order_no],
                        'attempts' => 0,
                    ]);

                    if (! $isPterodactyl && $service->nat_ip) {
                        ProvisionTask::create([
                            'service_id' => $service->id,
                            'operation' => 'sync_nat',
                            'status' => 'queued',
                            'payload' => ['order_no' => $lockedOrder->order_no],
                            'attempts' => 0,
                        ]);
                    }
                }
            }
            return true;
        });
        if ($fulfilled) {
            $notice = Notice::firstOrCreate(['dedupe_key'=>'payment_succeeded:'.$order->id],['user_id'=>$customer->id,'type'=>'payment_succeeded','title'=>'订单支付成功','body'=>'订单 '.$order->order_no.' 已支付成功。','data'=>['order_id'=>$order->id]]);
            if ($notice->wasRecentlyCreated) { defer(fn () => app(NotificationDispatcher::class)->dispatch($notice)); }
        }
        return $order->refresh();
    }
}
