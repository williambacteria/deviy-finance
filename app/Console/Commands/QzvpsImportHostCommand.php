<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPrice;
use App\Models\Service;
use App\Models\User;
use App\Services\Qzvps\QzvpsClient;
use App\Support\DocumentNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class QzvpsImportHostCommand extends Command
{
    protected $signature = 'qzvps:import-host
        {host_id : 轻舟主机 ID}
        {email : 迁移后的客户邮箱}
        {--password= : 新客户密码，默认随机生成}
        {--product-code= : 复用已有商品编码}
        {--product-name= : 新建商品名称}
        {--group=迁移资源 : 新建商品使用的一级分组名称}';

    protected $description = '从轻舟 VPS 导入一台已开通主机，并创建客户、商品和本地服务记录';

    public function handle(QzvpsClient $client): int
    {
        $hostId = (int) $this->argument('host_id');
        $email = Str::lower(trim((string) $this->argument('email')));
        if ($hostId <= 0 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('host_id 或 email 不合法。');

            return self::FAILURE;
        }

        try {
            $info = (array) ($client->hostInfo($hostId)['data'] ?? []);
        } catch (Throwable $exception) {
            $this->error('读取轻舟主机失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $hostName = (string) ($info['host_name'] ?? 'qzvps-'.$hostId);
        $password = (string) ($this->option('password') ?: Str::password(12, symbols: false));
        $productCode = (string) ($this->option('product-code') ?: 'QZ-'.$hostId);
        $productName = (string) ($this->option('product-name') ?: (($info['node_name'] ?? '轻舟').' / '.($info['line_name'] ?? 'VPS')));

        $result = DB::transaction(function () use ($client, $hostId, $email, $password, $productCode, $productName, $info, $hostName): array {
            $user = User::withTrashed()->where('email', $email)->first();
            if ($user?->trashed()) {
                $user->restore();
            }
            if (! $user) {
                $user = User::create([
                    'name' => Str::before($email, '@'),
                    'email' => $email,
                    'password' => $password,
                    'pterodactyl_password' => $password,
                    'status' => 'active',
                    'real_name_status' => 'unverified',
                    'customer_group' => 'default',
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
                $user->forceFill([
                    'status' => 'active',
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }

            $product = Product::where('code', $productCode)->first();
            if (! $product) {
                $group = ProductGroup::firstOrCreate(
                    ['parent_id' => null, 'name' => (string) $this->option('group')],
                    ['slug' => Str::slug((string) $this->option('group')).'-'.Str::lower(Str::random(4)), 'sort_order' => 999, 'is_visible' => true],
                );
                $specs = $this->specs($info);
                $product = Product::create([
                    'product_group_id' => $group->id,
                    'name' => $productName,
                    'code' => $productCode,
                    'type' => ((int) ($info['is_nat'] ?? 0) === 1) ? 'nat_vm' : 'cloud_vm',
                    'region' => (string) ($info['line_name'] ?? $info['area_name'] ?? '绍兴'),
                    'short_description' => implode(' / ', [
                        $specs['cpu'].'C',
                        $specs['memory_gb'].'G',
                        $specs['disk_gb'].'G',
                        ($specs['upload_mbps'] ?? 0).'Mbps 上行',
                    ]),
                    'description' => '从轻舟 VPS 迁移导入的商品。',
                    'stock' => 999,
                    'status' => 'active',
                    'sort_order' => 999,
                    'upstream_source' => 'qzvps',
                    'upstream_product_id' => (string) ($info['product_id'] ?? ''),
                    'metadata' => [
                        'qzvps' => [
                            'line_id' => (string) ($info['line_id'] ?? ''),
                            'node_id' => (string) ($info['node_id'] ?? ''),
                            'os_name' => (string) ($info['os_name'] ?? 'Ubuntu22.04'),
                            'ipnum' => (int) ($info['is_nat'] ?? 1) === 1 ? 0 : 1,
                            'port_num' => (int) ($info['port_num'] ?? 10),
                            'is_nat' => (int) ($info['is_nat'] ?? 0),
                        ],
                        'specs' => $specs,
                        'details' => [
                            ['label' => 'CPU', 'value' => ($info['cpu'] ?? '').' vCPU'],
                            ['label' => '内存', 'value' => ($info['memory'] ?? '').' GB'],
                            ['label' => '硬盘', 'value' => ($info['hard_disks'] ?? '').' GB'],
                            ['label' => '系统', 'value' => (string) ($info['os_name'] ?? '')],
                        ],
                    ],
                ]);
                foreach (['monthly' => 0, 'quarterly' => 0, 'semiannually' => 0, 'annually' => 0] as $cycle => $price) {
                    ProductPrice::create([
                        'product_id' => $product->id,
                        'billing_cycle' => $cycle,
                        'price' => $price,
                        'is_active' => true,
                    ]);
                }
            }

            $service = Service::withTrashed()->where('external_ref', 'qzvps:'.$hostId)->first();
            $specs = $this->specs($info);
            $address = $this->remoteAddress($info);
            $specs['internal_ip'] = (string) ($info['ip'] ?? '');
            $specs['qzvps'] = [
                'host_id' => $hostId,
                'line_id' => (int) ($info['line_id'] ?? 0),
                'node_id' => (int) ($info['node_id'] ?? 0),
                'host_name' => $hostName,
                'os_name' => (string) ($info['os_name'] ?? ''),
                'is_nat' => (int) ($info['is_nat'] ?? 0),
                'remote_ip' => (string) ($info['remote_ip'] ?? ''),
            ];
            $specs['remote'] = array_merge((array) ($specs['remote'] ?? []), [
                'address' => $address['host'],
                'port' => $address['port'],
                'username' => str_contains(strtolower((string) ($info['os_name'] ?? '')), 'windows') ? 'Administrator' : 'root',
                'password' => (string) ($info['os_password'] ?? ''),
            ]);

            $serviceData = [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'name' => (string) ($info['host_name'] ?? $product->name),
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'auto_renew' => false,
                'region' => (string) ($info['line_name'] ?? $info['area_name'] ?? $product->region),
                'ip_address' => (string) ($info['ip'] ?? ''),
                'nat_ip' => $address['host'] ?: null,
                'nat_port_start' => $address['port'] ?: null,
                'nat_port_end' => $address['port'] ?: null,
                'bandwidth_mbps' => (int) ($specs['download_mbps'] ?? 0),
                'os' => (string) ($info['os_name'] ?? ''),
                'specs' => $specs,
                'expires_at' => $info['end_time'] ?? null,
                'provision_status' => 'succeeded',
                'external_ref' => 'qzvps:'.$hostId,
            ];
            if ($service) {
                if ($service->trashed()) {
                    $service->restore();
                }
                $service->update($serviceData);
            } else {
                $service = Service::create($serviceData + [
                    'service_no' => 'QZ-'.$hostId.'-'.Str::upper(Str::random(4)),
                ]);
            }

            if (! $service->order_id) {
                $order = Order::create([
                    'order_no' => DocumentNumber::make('ORD'),
                    'user_id' => $user->id,
                    'status' => 'paid',
                    'type' => 'purchase',
                    'subtotal' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => 0,
                    'currency' => 'CNY',
                    'paid_at' => now(),
                ]);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'service_id' => $service->id,
                    'product_name' => $product->name,
                    'billing_cycle' => 'monthly',
                    'unit_price' => 0,
                    'quantity' => 1,
                    'amount' => 0,
                    'configuration' => ['source' => 'qzvps', 'host_id' => $hostId],
                ]);
                Invoice::create([
                    'invoice_no' => DocumentNumber::make('INV'),
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'status' => 'paid',
                    'amount' => 0,
                    'paid_amount' => 0,
                    'due_at' => now(),
                    'paid_at' => now(),
                    'items' => [['name' => $product->name, 'amount' => 0]],
                ]);
                $service->update(['order_id' => $order->id]);
            }

            return compact('user', 'product', 'service');
        });

        $this->info('导入完成。');
        $this->line('客户：'.$result['user']->email.'（ID '.$result['user']->id.'）');
        $this->line('商品：'.$result['product']->name.'（ID '.$result['product']->id.'）');
        $this->line('服务：'.$result['service']->service_no.'（ID '.$result['service']->id.'，qzvps:'.$hostId.'）');
        if (! $this->option('password')) {
            $this->warn('客户密码：'.$password);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function specs(array $info): array
    {
        return [
            'cpu' => (int) ($info['cpu'] ?? 1),
            'memory_gb' => (int) ($info['memory'] ?? 1),
            'disk_gb' => (int) ($info['hard_disks'] ?? 40),
            'data_disk_gb' => 0,
            'bandwidth_mbps' => (int) ($info['bandwidth'] ?? 10),
            'upload_mbps' => (int) ($info['bandwidth'] ?? 10),
            'download_mbps' => (int) ($info['bandwidth_in'] ?? 100),
            'nat_ports' => (int) ($info['port_num'] ?? 10),
            'ostype' => (string) ($info['os_name'] ?? 'Ubuntu22.04'),
        ];
    }

    /**
     * @return array{host: string, port: int}
     */
    private function remoteAddress(array $info): array
    {
        $remote = (string) ($info['remote_ip'] ?? '');
        if ($remote === '') {
            return ['host' => '', 'port' => 0];
        }

        if (str_contains($remote, ':')) {
            [$host, $port] = explode(':', $remote, 2);

            return ['host' => $host, 'port' => (int) $port];
        }

        return ['host' => $remote, 'port' => 0];
    }
}
