<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CloudNode;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Models\UpstreamOrder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\IpAllocator;
use App\Services\Commerce\ResourceCleanupService;
use App\Services\Mail\EmailVerificationService;
use App\Services\Mail\MailSettings;
use App\Services\Provisioning\ProvisionTaskDispatcher;
use App\Support\Billing;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\ProductOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();

        $customers = User::query()
            ->withCount(['services', 'orders', 'tickets'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('qq', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")))
            ->when($status === 'online', fn ($query) => $query->where('status', 'active')->where('last_seen_at', '>=', now()->subMinutes(5)))
            ->when($status === 'offline', fn ($query) => $query->where('status', 'active')->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(5))))
            ->when($status === 'disabled', fn ($query) => $query->where('status', '!=', 'active'))
            ->when(! in_array($status, ['online', 'offline', 'disabled'], true), fn ($query) => $query->when($status !== '', fn ($query) => $query->where('status', $status)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search', 'status'));
    }

    public function impersonate(Request $request, User $customer, AuditLogger $audit): RedirectResponse
    {
        $admin = $request->user('admin');
        if (! $admin) {
            abort(403);
        }

        $previousUserId = Auth::guard('web')->id();

        Auth::guard('web')->login($customer);
        $request->session()->put([
            'impersonator_admin_id' => $admin->id,
            'impersonator_admin_name' => $admin->name,
            'impersonator_customer_id' => $customer->id,
            'impersonator_previous_user_id' => $previousUserId,
        ]);
        $request->session()->regenerate();

        $audit->record($admin, 'customer.impersonated', '以客户身份登录：'.$customer->name, $customer);

        $redirect = (string) $request->input('redirect', '');
        $redirectPath = $redirect !== '' ? (parse_url($redirect, PHP_URL_PATH) ?: '') : '';
        if ($redirectPath !== '' && str_starts_with($redirectPath, '/console/') && ! str_contains($redirectPath, '//')) {
            return redirect()->to($redirectPath)->with('status', '已切换为客户身份：'.$customer->name);
        }

        return redirect()->route('console.dashboard')->with('status', '已切换为客户身份：'.$customer->name);
    }

    public function leaveImpersonation(Request $request, AuditLogger $audit): RedirectResponse
    {
        $adminId = (int) $request->session()->get('impersonator_admin_id');
        $customerId = (int) $request->session()->get('impersonator_customer_id');
        $previousUserId = (int) $request->session()->get('impersonator_previous_user_id');
        $admin = $adminId ? Admin::find($adminId) : null;

        $request->session()->forget(Auth::guard('web')->getName());
        $request->session()->forget('password_hash_web');
        if ($previousUserId && ($previousUser = User::find($previousUserId))) {
            Auth::guard('web')->login($previousUser);
        }

        $request->session()->forget([
            'impersonator_admin_id',
            'impersonator_admin_name',
            'impersonator_customer_id',
            'impersonator_previous_user_id',
        ]);
        $request->session()->regenerate();

        if ($admin) {
            $audit->record($admin, 'customer.impersonation_left', '退出客户身份查看', null);
        }

        if ($adminId && Auth::guard('admin')->check()) {
            return $customerId
                ? redirect()->route('admin.customers.show', $customerId)
                : redirect()->route('admin.customers.index');
        }

        return redirect()->route('admin.login');
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'qq' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'in:active,suspended'],
            'customer_group' => ['required', 'in:default,vip'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'send_verification' => ['nullable', 'boolean'],
        ]);

        $customer = DB::transaction(function () use ($request, $data, $audit): User {
            $initialBalance = (float) ($data['initial_balance'] ?? 0);
            $customer = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'qq' => $data['qq'] ?? null,
                'company' => $data['company'] ?? null,
                'password' => $data['password'],
                'pterodactyl_password' => $data['password'],
                'email_verified_at' => $request->boolean('send_verification') ? null : now(),
                'status' => $data['status'],
                'customer_group' => $data['customer_group'],
                'credit_limit' => $data['credit_limit'] ?? 0,
                'balance' => 0,
                'notes' => $data['notes'] ?? null,
                'metadata' => ['source' => 'admin_created'],
            ]);

            if ($initialBalance > 0) {
                $customer->update(['balance' => $initialBalance]);
                LedgerEntry::create([
                    'user_id' => $customer->id,
                    'type' => 'adjustment',
                    'amount' => $initialBalance,
                    'balance_after' => $initialBalance,
                    'reference_type' => User::class,
                    'reference_id' => $customer->id,
                    'description' => '管理员开户初始余额',
                ]);
            }

            $audit->record($request->user('admin'), 'customer.created', '创建客户：'.$customer->name, $customer);

            return $customer;
        });

        if ($request->boolean('send_verification')) {
            app(EmailVerificationService::class)->send($customer);
            MailSettings::sendTemplate('account_registered', $customer, [
                'login_url' => route('login'),
            ]);
        }

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', '客户已创建。');
    }

    public function show(Request $request, User $customer): View
    {
        $customer->loadCount(['services', 'orders', 'tickets']);
        $customer->load([
            'orders' => fn ($query) => $query->latest()->limit(20),
            'tickets' => fn ($query) => $query->latest()->limit(20),
            'ledgerEntries' => fn ($query) => $query->latest()->limit(10),
        ]);

        $serviceSearch = $request->string('service_q')->trim()->toString();
        $serviceStatus = $request->string('service_status')->toString();
        $services = $customer->services()
            ->with(['product.group.parent.parent', 'node', 'natMappings'])
            ->when($serviceSearch !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$serviceSearch}%")
                ->orWhere('service_no', 'like', "%{$serviceSearch}%")
                ->orWhere('ip_address', 'like', "%{$serviceSearch}%")
                ->orWhere('nat_ip', 'like', "%{$serviceSearch}%")))
            ->when($serviceStatus !== '', fn ($query) => $query->where('status', $serviceStatus))
            ->latest('id')
            ->paginate(10, ['*'], 'service_page')
            ->withQueryString();

        $serviceRenewalQuotes = [];
        foreach ($services as $listedService) {
            try {
                $serviceRenewalQuotes[$listedService->id] = app(\App\Services\Billing\RenewalService::class)->quote($listedService, $listedService->billing_cycle ?: 'monthly')['amount'];
            } catch (ValidationException) {
                $serviceRenewalQuotes[$listedService->id] = null;
            }
        }

        return view('admin.customers.show', [
            'customer' => $customer,
            'products' => Product::query()
                ->where('status', 'active')
                ->with([
                    'group.parent',
                    'prices' => fn ($query) => $query->where('is_active', true)->orderBy('price'),
                ])
                ->orderBy('name')
                ->get(),
            'nodes' => CloudNode::query()->orderBy('name')->get(),
            'services' => $services,
            'serviceSearch' => $serviceSearch,
            'serviceStatus' => $serviceStatus,
            'serviceRenewalQuotes' => $serviceRenewalQuotes,
        ]);
    }

    public function updateService(Request $request, User $customer, Service $service, AuditLogger $audit): RedirectResponse
    {
        abort_unless($service->user_id === $customer->id, 404);
        abort_unless($request->user('admin')?->canOperateServices(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'expires_at' => ['nullable', 'date'],
            'auto_renew' => ['required', 'boolean'],
            'renewal_mode' => ['required', 'in:keep,custom'],
            'renewal_amount' => ['nullable', 'required_if:renewal_mode,custom', 'numeric', 'min:0', 'max:9999999'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        return \Illuminate\Support\Facades\Cache::lock('customer-service-delete:'.$service->id, 180)->block(3, function () use ($request, $service, $data, $audit): RedirectResponse {
            $service->refresh();
            if (data_get($service->specs, 'admin_deletion')) {
                throw ValidationException::withMessages(['service' => '服务正在核对销毁结果，暂不能调整，请先处理删除任务。']);
            }
            $specs = (array) $service->specs;
            $before = $service->only(['name', 'expires_at', 'auto_renew']);
            $cycle = $service->billing_cycle ?: 'monthly';
            $before['renewal_amount'] = data_get($specs, 'legacy_import.renewal_prices.'.$cycle)
                ?? data_get($specs, 'contract_prices.'.$cycle) ?? data_get($specs, 'renewal_amount');
            if ($data['renewal_mode'] === 'custom') {
                $amount = number_format((float) $data['renewal_amount'], 2, '.', '');
                if (! $service->product?->prices()->where('billing_cycle', $cycle)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['renewal_amount' => '该商品当前周期未启用，不能设置续费价。']);
                }
                if (data_get($specs, 'legacy_import.source') === 'www.deviy.cn' && is_array(data_get($specs, 'legacy_import.renewal_prices'))) {
                    $specs['legacy_import']['renewal_prices'][$cycle] = $amount;
                } else {
                    $specs['contract_prices'][$cycle] = $amount;
                }
            }
            if ((bool) $data['auto_renew'] && (data_get($specs, 'legacy_import.operation_hold', false) || ! in_array($service->status, ['active', 'suspended', 'stopped'], true))) {
                throw ValidationException::withMessages(['auto_renew' => '当前服务状态不能启用自动续费。']);
            }
            $updates = ['name' => trim($data['name']), 'auto_renew' => (bool) $data['auto_renew'], 'specs' => $specs];
            if ($service->product?->upstream_source !== 'mofang') {
                $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;
                if ($expiresAt && $service->expires_at?->format('Y-m-d H:i') === $expiresAt->format('Y-m-d H:i')) {
                    $expiresAt = $service->expires_at->copy();
                }
                if ($service->product?->upstream_source === 'qzvps') {
                    if (! $expiresAt || data_get($specs, 'legacy_import.operation_hold', false) || ! in_array($service->status, ['active', 'suspended', 'stopped'], true)) {
                        throw ValidationException::withMessages(['expires_at' => '轻舟商品需填写有效到期日期，且实例不能处于核查或结束状态。']);
                    }
                    if (! preg_match('/^qzvps:([1-9]\d*)$/D', (string) $service->external_ref, $binding)) {
                        throw ValidationException::withMessages(['expires_at' => '轻舟主机绑定不完整，不能同步到期时间。']);
                    }
                    $hostId = (int) $binding[1];
                    $client = app(\App\Services\Qzvps\QzvpsClient::class);
                    try {
                        $host = (array) data_get($client->hostInfo($hostId), 'data', []);
                        if ((int) ($host['id'] ?? 0) !== $hostId) {
                            throw new \RuntimeException('主机编号核对失败。');
                        }
                        $remoteDate = substr((string) ($host['end_time'] ?? ''), 0, 10);
                        if ($remoteDate !== $expiresAt->toDateString()) {
                            $client->renew($hostId, $expiresAt->format('Y-m-d H:i:s'));
                            $host = (array) data_get($client->hostInfo($hostId), 'data', []);
                        }
                        if ((int) ($host['id'] ?? 0) !== $hostId || substr((string) ($host['end_time'] ?? ''), 0, 10) !== $expiresAt->toDateString()) {
                            throw new \RuntimeException('轻舟尚未确认新到期日期。');
                        }
                        $specs['qzvps'] = array_merge((array) ($specs['qzvps'] ?? []), ['end_time' => $host['end_time'], 'state' => $host['state'] ?? null, 'synced_at' => now()->toIso8601String()]);
                        $updates['specs'] = $specs;
                        if ($expiresAt->isFuture()) {
                            $updates['grace_ends_at'] = null;
                        }
                    } catch (\Throwable $exception) {
                        $audit->record($request->user('admin'), 'service.expiry_sync_unconfirmed', '轻舟到期日期同步未确认：'.$service->service_no, $service, ['desired_expiry' => $expiresAt->toIso8601String()]);
                        throw ValidationException::withMessages(['expires_at' => '轻舟到期日期同步未确认，本站未保存本次调整。请核对轻舟实际状态后再试。']);
                    }
                }
                $updates['expires_at'] = $expiresAt;
                $updates['next_due_at'] = $expiresAt?->copy()->subDays(7);
            }
            $service->update($updates);
            $audit->record($request->user('admin'), 'customer.service_updated', '调整客户服务：'.$service->service_no, $service, ['reason' => $data['reason'], 'before' => $before, 'after' => $service->only(['name', 'expires_at', 'auto_renew']), 'renewal_mode' => $data['renewal_mode'], 'renewal_amount' => $data['renewal_mode'] === 'custom' ? $data['renewal_amount'] : null, 'billing_cycle' => $cycle]);

            return back()->with('status', '服务资料与续费设置已更新，调整原因已记入操作日志。');
        });
    }

    public function deleteService(Request $request, User $customer, Service $service, AuditLogger $audit, \App\Services\Commerce\CustomerServiceDeletion $deletion): RedirectResponse
    {
        abort_unless($service->user_id === $customer->id, 404);
        abort_unless($request->user('admin')?->canOperateServices(), 403);
        $data = $request->validate([
            'confirm_service_no' => ['required', Rule::in([$service->service_no])],
            'acknowledge' => ['required', 'accepted'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
        ], ['confirm_service_no.in' => '输入的服务编号不一致，请重新核对。', 'acknowledge.accepted' => '请确认已了解资源销毁范围。']);
        try {
            $result = $deletion->delete($service, $request->user('admin'), $data['reason']);
        } catch (\Throwable $exception) {
            $audit->record($request->user('admin'), 'customer.service_delete_blocked', '删除被阻止：'.$service->service_no, $service, ['reason' => $data['reason']]);

            return back()->withErrors(['service' => $exception instanceof \RuntimeException ? $exception->getMessage() : '删除暂时无法执行，服务记录已保留，请稍后核对。']);
        }

        return $result['completed'] ? back()->with('status', $result['message']) : back()->withErrors(['service' => $result['message']]);
    }

    public function storeService(Request $request, User $customer, AuditLogger $audit, InventoryService $inventory, IpAllocator $ipAllocator): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semiannual,annual'],
            'first_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'renewal_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'ip_mode' => ['nullable', 'in:shared,dedicated'],
            'nat_ports' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'data_disk_gb' => ['nullable', 'integer', 'min:0', 'max:65536'],
            'ostype' => ['nullable', 'string', 'max:32'],
            'node_id' => ['nullable', 'exists:cloud_nodes,id'],
            'region' => ['nullable', 'string', 'max:64'],
            'ip_address' => ['nullable', 'ip'],
            'bandwidth_mbps' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'auto_renew' => ['nullable', 'boolean'],
            'upstream_os' => ['nullable', 'string', 'max:190'],
            'upstream_config' => ['nullable', 'array'],
            'upstream_config.*' => ['nullable', 'string', 'max:80'],
        ]);

        $product = Product::query()
            ->with(['prices' => fn ($query) => $query->where('is_active', true)->orderBy('price')])
            ->findOrFail($data['product_id']);
        $price = $product->prices->firstWhere('billing_cycle', $data['billing_cycle']);
        if (! $price || $product->status !== 'active') {
            throw ValidationException::withMessages(['billing_cycle' => '该商品当前周期不可购买，请重新选择。']);
        }

        $isUpstream = $product->upstream_source === 'mofang' || data_get($product->metadata, 'upstream') === 'mofang';
        $isPterodactyl = $product->upstream_source === 'pterodactyl';
        $controllerNodeId = trim((string) data_get($product->metadata, 'controller.node_id'));
        $node = null;
        if (! $isUpstream && ! $isPterodactyl) {
            $node = $controllerNodeId !== ''
                ? CloudNode::query()->where('agent_node_id', $controllerNodeId)->first()
                : CloudNode::query()->find($data['node_id'] ?? 0);
            if (! $node) {
                throw ValidationException::withMessages(['node_id' => '主控商品必须选择宿主机节点。']);
            }
        }

        $months = Billing::months($data['billing_cycle']);
        $optionInput = ! empty($data['ostype']) ? ['ostype' => $data['ostype']] : [];
        $configuration = ProductOptions::resolve($product, $optionInput);
        $configuration['upstream_os'] = $data['upstream_os'] ?? null;
        $upstreamConfig = is_array($data['upstream_config'] ?? null) ? $data['upstream_config'] : [];
        if ($isUpstream && $upstreamConfig === []) {
            $upstreamConfig = collect((array) data_get($product->metadata, 'upstream_config_groups', []))
                ->mapWithKeys(function (array $group): array {
                    $value = data_get($group, 'options.0.value');

                    return $value === null ? [] : [(string) ($group['id'] ?? '') => (string) $value];
                })
                ->filter()
                ->all();
        }
        $configuration['upstream_config'] = $upstreamConfig;
        $specs = array_merge([
            'cpu' => 2,
            'memory_gb' => 4,
            'disk_gb' => 40,
            'data_disk_gb' => 0,
            'bandwidth_mbps' => 10,
        ], is_array($product->metadata['specs'] ?? null) ? $product->metadata['specs'] : [], $configuration['specs'] ?? []);
        $ostype = ProductOptions::controllerOSType((string) ($configuration['ostype'] ?? 'l26'));
        $specs['ostype'] = $ostype;
        $specs['data_disk_gb'] = (int) ($data['data_disk_gb'] ?? $specs['data_disk_gb'] ?? 0);
        $ipMode = (string) ($data['ip_mode'] ?? 'shared');
        $ipAddress = $ipMode === 'dedicated' ? ($data['ip_address'] ?? null) : null;
        if ($ipMode === 'shared') {
            $specs['nat_ports'] = (int) ($data['nat_ports'] ?? $specs['nat_ports'] ?? 20);
        }
        $specs['remote'] = [
            'address' => '',
            'port' => null,
            'username' => str_starts_with($ostype, 'win') ? 'Administrator' : 'root',
            'password' => Str::password(16),
            'protocol' => str_starts_with($ostype, 'win') ? 'rdp' : 'ssh',
            'note' => '',
        ];
        $expiresAt = now()->addMonths($months);
        $renewalPrice = (float) $data['renewal_price'];
        if ($renewalPrice > 0) {
            $discount = (float) (ProductOptions::cycleDiscounts($product)[$data['billing_cycle']] ?? 1);
            $specs['renewal_amount'] = round($renewalPrice / max(0.000001, $months * $discount), 6);
        }
        $amount = number_format((float) $data['first_price'], 2, '.', '');

        $service = DB::transaction(function () use ($request, $customer, $product, $data, $amount, $configuration, $specs, $ostype, $ipAddress, $expiresAt, $node, $isUpstream, $isPterodactyl, $inventory, $ipAllocator): Service {
            $lockedCustomer = User::query()->lockForUpdate()->findOrFail($customer->id);
            if (Money::compare($lockedCustomer->balance, $amount) < 0) {
                throw ValidationException::withMessages([
                    'balance' => '客户账户余额不足，无法开通。当前余额 ¥'.number_format((float) $lockedCustomer->balance, 2).'，首付需要 ¥'.number_format((float) $amount, 2).'。',
                ]);
            }

            $balanceAfter = Money::subtract($lockedCustomer->balance, $amount);
            $lockedCustomer->update(['balance' => $balanceAfter]);

            $order = Order::create([
                'order_no' => DocumentNumber::make('ORD'),
                'user_id' => $customer->id,
                'status' => 'paid',
                'type' => 'purchase',
                'subtotal' => $amount,
                'discount' => 0,
                'tax' => 0,
                'total' => $amount,
                'currency' => 'CNY',
                'paid_at' => now(),
            ]);

            $service = Service::create([
                'service_no' => DocumentNumber::make('SVC'),
                'user_id' => $customer->id,
                'product_id' => $product->id,
                'order_id' => $order->id,
                'node_id' => $isUpstream || $isPterodactyl ? null : $node?->id,
                'name' => $product->name,
                'status' => 'provisioning',
                'billing_cycle' => $data['billing_cycle'],
                'auto_renew' => $request->boolean('auto_renew'),
                'region' => $data['region'] ?? $product->region,
                'ip_address' => $ipAddress,
                'bandwidth_mbps' => (int) ($data['bandwidth_mbps'] ?? $specs['download_mbps'] ?? $specs['bandwidth_mbps'] ?? 10),
                'os' => $isUpstream && ! empty($data['upstream_os']) ? $data['upstream_os'] : (string) ($configuration['selection']['ostype']['label'] ?? 'Linux'),
                'specs' => $specs,
                'expires_at' => $expiresAt,
                'next_due_at' => $expiresAt->copy()->subDays((int) ($product->metadata['expiry_reminder_days'] ?? 7)),
                'provision_status' => $isPterodactyl ? 'pterodactyl_queued' : ($isUpstream ? 'upstream_queued' : 'queued'),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'service_id' => $service->id,
                'product_name' => $product->name,
                'billing_cycle' => $data['billing_cycle'],
                'unit_price' => $amount,
                'quantity' => 1,
                'amount' => $amount,
                'configuration' => [
                    'region' => $service->region,
                    'specs' => $specs,
                    'ostype' => $ostype,
                    'options' => $configuration['selection'] ?? [],
                    'upstream_os' => $configuration['upstream_os'] ?? null,
                    'upstream_config' => $configuration['upstream_config'] ?? [],
                    'source' => 'admin_purchase',
                ],
            ]);

            $invoice = Invoice::create([
                'invoice_no' => DocumentNumber::make('INV'),
                'user_id' => $customer->id,
                'order_id' => $order->id,
                'status' => 'paid',
                'amount' => $amount,
                'paid_amount' => $amount,
                'due_at' => now(),
                'paid_at' => now(),
                'items' => [['name' => $product->name, 'amount' => (float) $amount]],
            ]);

            Payment::create([
                'payment_no' => DocumentNumber::make('PAY'),
                'user_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'gateway' => 'balance',
                'amount' => $amount,
                'status' => 'succeeded',
                'transaction_id' => 'BAL-'.strtoupper(uniqid()),
                'paid_at' => now(),
                'payload' => ['source' => 'admin_customer_purchase', 'admin_id' => $request->user('admin')->id],
            ]);

            LedgerEntry::create([
                'user_id' => $customer->id,
                'type' => 'payment',
                'amount' => '-'.$amount,
                'balance_after' => $balanceAfter,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'description' => '后台为客户开通商品 '.$product->name.'（'.$order->order_no.'）',
            ]);

            $inventory->reserve($order, $product, 1);
            $inventory->consume($order);

            if ($isUpstream) {
                UpstreamOrder::create([
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'product_id' => $product->id,
                    'source' => 'mofang',
                    'status' => 'pending',
                    'amount' => $amount,
                    'billing_cycle' => $data['billing_cycle'],
                    'payload' => [
                        'product_id' => $product->upstream_product_id,
                        'quantity' => 1,
                        'billing_cycle' => $data['billing_cycle'],
                        'customer_email' => $customer->email,
                        'service_no' => $service->service_no,
                        'order_no' => $order->order_no,
                        'os' => $configuration['upstream_os'] ?? null,
                        'configoption' => $configuration['upstream_config'] ?? [],
                    ],
                ]);

                return $service;
            }

            if (! $isPterodactyl) {
                if (empty($service->ip_address)) {
                    $ipAllocator->allocate($node, $service, $product);
                    $service->refresh();
                }

                ProvisionTask::create([
                    'service_id' => $service->id,
                    'operation' => 'create',
                    'status' => 'queued',
                    'payload' => ['source' => 'admin_customer_purchase', 'order_no' => $order->order_no],
                    'attempts' => 0,
                ]);

                if ($service->nat_ip) {
                    ProvisionTask::create([
                        'service_id' => $service->id,
                        'operation' => 'sync_nat',
                        'status' => 'queued',
                        'payload' => ['source' => 'admin_customer_purchase', 'order_no' => $order->order_no],
                        'attempts' => 0,
                    ]);
                }
            } else {
                ProvisionTask::create([
                    'service_id' => $service->id,
                    'operation' => 'create',
                    'status' => 'queued',
                    'payload' => ['source' => 'admin_customer_purchase', 'order_no' => $order->order_no],
                    'attempts' => 0,
                ]);
            }

            return $service;
        });

        $audit->record(
            $request->user('admin'),
            'customer.service_created',
            '为客户 '.$customer->name.' 开通商品 '.$product->name.'（'.$service->service_no.'）',
            $service,
        );

        return back()->with('status', '商品已按购买流程开通，服务编号 '.$service->service_no.'。');
    }

    public function verifyRealName(Request $request, User $customer, AuditLogger $audit): RedirectResponse
    {
        if ($customer->real_name_status !== 'pending') {
            return back()->withErrors(['real_name' => '该客户当前没有待审核的实名资料。']);
        }

        $customer->update([
            'real_name_status' => 'verified',
            'real_name_verified_at' => now(),
            'real_name_reject_reason' => null,
        ]);
        $audit->record($request->user('admin'), 'customer.real_name_verified', '通过客户实名认证：'.$customer->name, $customer);

        return back()->with('status', '实名认证已通过。');
    }

    public function rejectRealName(Request $request, User $customer, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($customer->real_name_status !== 'pending') {
            return back()->withErrors(['real_name' => '该客户当前没有待审核的实名资料。']);
        }

        $customer->update([
            'real_name_status' => 'rejected',
            'real_name_verified_at' => null,
            'real_name_reject_reason' => $data['reason'],
        ]);
        $audit->record($request->user('admin'), 'customer.real_name_rejected', '驳回客户实名认证：'.$customer->name, $customer);

        return back()->with('status', '实名认证已驳回。');
    }

    public function realNameFile(Request $request, User $customer, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['license', 'legal_id'], true), 404);
        $file = $type === 'license' ? $customer->real_name_license : $customer->real_name_legal_id;
        abort_unless(is_array($file) && ! empty($file['path']), 404);

        $disk = (string) ($file['disk'] ?? 'local');
        abort_unless(Storage::disk($disk)->exists((string) $file['path']), 404);

        return Storage::disk($disk)->download(
            (string) $file['path'],
            (string) ($file['name'] ?? ($type === 'license' ? '营业执照' : '法人身份证')),
            ['Content-Type' => (string) ($file['mime'] ?? 'application/octet-stream')],
        );
    }

    public function update(Request $request, User $customer, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'qq' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,suspended,closed'],
            'customer_group' => ['required', 'string', 'max:32'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer->update($data);
        $audit->record($request->user('admin'), 'customer.updated', '更新客户资料：'.$customer->name, $customer);

        return back()->with('status', '客户资料已更新。');
    }

    public function destroy(Request $request, User $customer, AuditLogger $audit): RedirectResponse
    {
        if ($customer->services()->exists()) {
            return back()->withErrors(['delete' => '该用户账户上有服务正在运行，请删除后再继续。']);
        }

        $name = $customer->name;
        $customer->delete();
        $audit->record($request->user('admin'), 'customer.deleted', '删除客户：'.$name, null);

        return redirect()->route('admin.customers.index')->with('status', '客户已删除。');
    }

    public function adjustBalance(Request $request, User $customer, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['required', 'string', 'max:160'],
        ]);

        DB::transaction(function () use ($request, $customer, $data, $audit) {
            $locked = User::query()->lockForUpdate()->findOrFail($customer->id);
            $newBalance = Money::add($locked->balance, $data['amount']);

            if (Money::compare($newBalance, '0') < 0) {
                abort(422, '调账后余额不能为负数。');
            }

            $locked->update(['balance' => $newBalance]);

            LedgerEntry::create([
                'user_id' => $locked->id,
                'type' => 'adjustment',
                'amount' => $data['amount'],
                'balance_after' => $newBalance,
                'reference_type' => User::class,
                'reference_id' => $locked->id,
                'description' => $data['description'],
            ]);

            $audit->record($request->user('admin'), 'customer.balance_adjusted', '调整余额 '.$data['amount'].'：'.$data['description'], $locked);
        });

        return back()->with('status', '余额已调整。');
    }

    public function sendPasswordReset(User $customer): RedirectResponse
    {
        return back()->with('status', '密码重置链接生成功能将在通知模块接入后启用。');
    }
}
