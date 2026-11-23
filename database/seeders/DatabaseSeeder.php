<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\CloudNode;
use App\Models\Invoice;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\LedgerEntry;
use App\Models\NatMapping;
use App\Models\NatPortAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Plugin;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPrice;
use App\Models\ProvisionTask;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\UpstreamProduct;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Admin::create([
            'name' => '超级管理员',
            'email' => 'admin@deviy.cn',
            'password' => 'admin123',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        Admin::create([
            'name' => '财务运营',
            'email' => 'finance@deviy.cn',
            'password' => 'finance123',
            'role' => 'finance',
            'is_active' => true,
        ]);

        Admin::create([
            'name' => '技术支持',
            'email' => 'support@deviy.cn',
            'password' => 'support123',
            'role' => 'support',
            'is_active' => true,
        ]);

        $customers = collect([
            [
                'name' => '林默',
                'email' => 'linmo@example.com',
                'phone' => '13800138001',
                'company' => '星海网络',
                'balance' => 2680,
                'credit_limit' => 5000,
                'real_name_status' => 'verified',
                'customer_group' => 'vip',
            ],
            [
                'name' => '陈屿',
                'email' => 'chenyu@example.com',
                'phone' => '13800138002',
                'company' => '屿游工作室',
                'balance' => 860,
                'credit_limit' => 2000,
                'real_name_status' => 'verified',
                'customer_group' => 'default',
            ],
            [
                'name' => '周宁',
                'email' => 'zhouning@example.com',
                'phone' => '13800138003',
                'company' => null,
                'balance' => 120,
                'credit_limit' => 0,
                'real_name_status' => 'pending',
                'customer_group' => 'default',
            ],
        ])->map(fn (array $data) => User::create($data + [
            'password' => 'customer123',
            'email_verified_at' => now(),
            'status' => 'active',
        ]));

        $groups = [
            'cloud' => ProductGroup::create([
                'name' => '云服务器',
                'slug' => 'cloud-server',
                'description' => 'KVM 架构、独立 IP、SSD 存储',
                'sort_order' => 1,
            ]),
            'nat' => ProductGroup::create([
                'name' => 'NAT 云机',
                'slug' => 'nat-cloud',
                'description' => '共享出口、端口转发、低成本入门',
                'sort_order' => 2,
            ]),
            'network' => ProductGroup::create([
                'name' => '网络与带宽',
                'slug' => 'network',
                'description' => '独立 IP、带宽升级、高防',
                'sort_order' => 3,
            ]),
        ];

        $products = collect([
            [
                'group' => 'cloud',
                'name' => '香港 CN2 · 2C4G',
                'code' => 'HK-CN2-2C4G',
                'type' => 'cloud_vm',
                'region' => '香港',
                'short_description' => '2 核 4G · 40G SSD · 10Mbps · 独立 IPv4',
                'stock' => 28,
                'status' => 'active',
                'prices' => ['monthly' => 128, 'quarterly' => 358, 'annually' => 1280],
            ],
            [
                'group' => 'cloud',
                'name' => '美国洛杉矶 · 4C8G',
                'code' => 'US-LA-4C8G',
                'type' => 'cloud_vm',
                'region' => '美国',
                'short_description' => '4 核 8G · 80G SSD · 30Mbps · 独立 IPv4',
                'stock' => 16,
                'status' => 'active',
                'prices' => ['monthly' => 198, 'quarterly' => 558, 'annually' => 1980],
            ],
            [
                'group' => 'nat',
                'name' => '香港 NAT · 1C1G',
                'code' => 'HK-NAT-1C1G',
                'type' => 'nat_vm',
                'region' => '香港',
                'short_description' => '1 核 1G · 20G SSD · 20 个端口 · 共享出口',
                'stock' => 120,
                'status' => 'active',
                'prices' => ['monthly' => 29, 'quarterly' => 79, 'annually' => 299],
            ],
            [
                'group' => 'nat',
                'name' => '日本 NAT · 2C2G',
                'code' => 'JP-NAT-2C2G',
                'type' => 'nat_vm',
                'region' => '日本',
                'short_description' => '2 核 2G · 30G SSD · 50 个端口 · 共享出口',
                'stock' => 64,
                'status' => 'active',
                'prices' => ['monthly' => 45, 'quarterly' => 125, 'annually' => 459],
            ],
            [
                'group' => 'network',
                'name' => '独立 IPv4 地址',
                'code' => 'ADDON-IPV4',
                'type' => 'dedicated_ip',
                'region' => '香港',
                'short_description' => '每台云机可加购独立 IPv4，含基础防护',
                'stock' => 200,
                'status' => 'active',
                'prices' => ['monthly' => 20, 'annually' => 200],
            ],
        ])->map(function (array $data) use ($groups) {
            $product = Product::create([
                'product_group_id' => $groups[$data['group']]->id,
                'name' => $data['name'],
                'code' => $data['code'],
                'type' => $data['type'],
                'region' => $data['region'],
                'short_description' => $data['short_description'],
                'description' => $data['short_description'],
                'stock' => $data['stock'],
                'status' => $data['status'],
                'upstream_source' => 'local',
            ]);

            foreach ($data['prices'] as $cycle => $price) {
                ProductPrice::create([
                    'product_id' => $product->id,
                    'billing_cycle' => $cycle,
                    'price' => $price,
                    'cost_price' => round($price * 0.62, 2),
                ]);
            }

            return $product;
        });

        $nodes = collect([
            CloudNode::create([
                'name' => 'PVE-HK-01',
                'agent_node_id' => 'pve-hk-01',
                'agent_url' => 'http://127.0.0.1:8443',
                'agent_mode' => 'mock',
                'provider' => 'pve',
                'host' => '10.10.1.11',
                'port' => 8006,
                'region' => '香港',
                'status' => 'online',
                'agent_version' => '0.1.0',
                'capacity' => ['cpu' => 64, 'memory_gb' => 256, 'storage_gb' => 4096, 'used_vms' => 42],
                'last_heartbeat_at' => now(),
            ]),
            CloudNode::create([
                'name' => 'PVE-JP-01',
                'agent_node_id' => 'pve-jp-01',
                'agent_url' => 'http://127.0.0.1:8445',
                'agent_mode' => 'mock',
                'provider' => 'pve',
                'host' => '10.10.2.11',
                'port' => 8006,
                'region' => '日本',
                'status' => 'online',
                'agent_version' => '0.1.0',
                'capacity' => ['cpu' => 32, 'memory_gb' => 128, 'storage_gb' => 2048, 'used_vms' => 18],
                'last_heartbeat_at' => now()->subMinute(),
            ]),
            CloudNode::create([
                'name' => 'HV-US-01',
                'agent_node_id' => 'hv-us-01',
                'agent_url' => 'http://127.0.0.1:8444',
                'agent_mode' => 'mock',
                'provider' => 'hyperv',
                'host' => '10.10.3.11',
                'port' => 5986,
                'region' => '美国',
                'status' => 'online',
                'agent_version' => '0.1.0',
                'capacity' => ['cpu' => 48, 'memory_gb' => 192, 'storage_gb' => 3072, 'used_vms' => 26],
                'last_heartbeat_at' => now()->subMinutes(2),
            ]),
        ]);

        $services = collect([
            Service::create([
                'service_no' => 'SVC-202611-0001',
                'user_id' => $customers[0]->id,
                'product_id' => $products[0]->id,
                'node_id' => $nodes[0]->id,
                'name' => '香港 CN2 · 生产环境',
                'status' => 'active',
                'region' => '香港',
                'ip_address' => '203.0.113.21',
                'bandwidth_mbps' => 10,
                'os' => 'Debian 13',
                'specs' => ['cpu' => 2, 'memory_gb' => 4, 'disk_gb' => 40],
                'expires_at' => now()->addDays(18),
                'next_due_at' => now()->addDays(11),
                'provision_status' => 'succeeded',
            ]),
            Service::create([
                'service_no' => 'SVC-202611-0002',
                'user_id' => $customers[0]->id,
                'product_id' => $products[2]->id,
                'node_id' => $nodes[0]->id,
                'name' => '香港 NAT · 测试环境',
                'status' => 'active',
                'region' => '香港',
                'nat_ip' => '203.0.113.88',
                'nat_port_start' => 20000,
                'nat_port_end' => 20019,
                'bandwidth_mbps' => 20,
                'os' => 'Ubuntu 24.04',
                'specs' => ['cpu' => 1, 'memory_gb' => 1, 'disk_gb' => 20],
                'expires_at' => now()->addDays(42),
                'next_due_at' => now()->addDays(35),
                'provision_status' => 'succeeded',
            ]),
            Service::create([
                'service_no' => 'SVC-202611-0003',
                'user_id' => $customers[1]->id,
                'product_id' => $products[1]->id,
                'node_id' => $nodes[2]->id,
                'name' => '美国洛杉矶 · 游戏服',
                'status' => 'active',
                'region' => '美国',
                'ip_address' => '198.51.100.42',
                'bandwidth_mbps' => 30,
                'os' => 'Windows Server 2022',
                'specs' => ['cpu' => 4, 'memory_gb' => 8, 'disk_gb' => 80],
                'expires_at' => now()->addDays(5),
                'next_due_at' => now()->addDays(-2),
                'provision_status' => 'succeeded',
            ]),
            Service::create([
                'service_no' => 'SVC-202611-0004',
                'user_id' => $customers[2]->id,
                'product_id' => $products[3]->id,
                'node_id' => $nodes[1]->id,
                'name' => '日本 NAT · 开发机',
                'status' => 'pending',
                'region' => '日本',
                'nat_ip' => '203.0.113.99',
                'nat_port_start' => 30000,
                'nat_port_end' => 30049,
                'bandwidth_mbps' => 10,
                'os' => 'Alpine 3.22',
                'specs' => ['cpu' => 2, 'memory_gb' => 2, 'disk_gb' => 30],
                'expires_at' => now()->addDays(30),
                'next_due_at' => now()->addDays(30),
                'provision_status' => 'queued',
            ]),
        ]);

        NatMapping::create([
            'service_id' => $services[1]->id,
            'external_ip' => '203.0.113.88',
            'external_port' => 20000,
            'internal_ip' => '10.0.0.12',
            'internal_port' => 22,
            'protocol' => 'tcp',
            'status' => 'active',
        ]);

        NatMapping::create([
            'service_id' => $services[1]->id,
            'external_ip' => '203.0.113.88',
            'external_port' => 20001,
            'internal_ip' => '10.0.0.12',
            'internal_port' => 80,
            'protocol' => 'tcp',
            'status' => 'active',
        ]);

        ProvisionTask::create([
            'service_id' => $services[0]->id,
            'operation' => 'create',
            'status' => 'succeeded',
            'payload' => ['node' => 'PVE-HK-01', 'template' => 'debian-13'],
            'attempts' => 1,
            'dispatched_at' => now()->subMinutes(8),
            'completed_at' => now()->subMinutes(7),
        ]);

        ProvisionTask::create([
            'service_id' => $services[1]->id,
            'operation' => 'sync_nat',
            'status' => 'succeeded',
            'payload' => ['node' => 'PVE-HK-01', 'rules' => 2],
            'attempts' => 1,
            'dispatched_at' => now()->subMinutes(6),
            'completed_at' => now()->subMinutes(6),
        ]);

        $orders = collect([
            Order::create([
                'order_no' => 'ORD-202611-0001',
                'user_id' => $customers[0]->id,
                'status' => 'paid',
                'subtotal' => 128,
                'discount' => 0,
                'tax' => 0,
                'total' => 128,
                'paid_at' => now()->subDays(12),
            ]),
            Order::create([
                'order_no' => 'ORD-202611-0002',
                'user_id' => $customers[1]->id,
                'status' => 'paid',
                'subtotal' => 198,
                'discount' => 20,
                'tax' => 0,
                'total' => 178,
                'paid_at' => now()->subDays(6),
            ]),
            Order::create([
                'order_no' => 'ORD-202611-0003',
                'user_id' => $customers[2]->id,
                'status' => 'pending',
                'subtotal' => 45,
                'discount' => 0,
                'tax' => 0,
                'total' => 45,
            ]),
        ]);

        $orders->each(function (Order $order, int $index) use ($products) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $products[$index]->id,
                'product_name' => $products[$index]->name,
                'billing_cycle' => 'monthly',
                'unit_price' => $order->subtotal,
                'quantity' => 1,
                'amount' => $order->subtotal,
            ]);
        });

        $invoices = $orders->map(fn (Order $order, int $index) => Invoice::create([
            'invoice_no' => 'INV-202611-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'status' => $order->status === 'paid' ? 'paid' : 'unpaid',
            'amount' => $order->total,
            'paid_amount' => $order->status === 'paid' ? $order->total : 0,
            'due_at' => now()->addDays($order->status === 'paid' ? 0 : 3),
            'paid_at' => $order->paid_at,
            'items' => [['name' => $products[$index]->name, 'amount' => (float) $order->total]],
        ]));

        Payment::create([
            'payment_no' => 'PAY-202611-0001',
            'user_id' => $customers[0]->id,
            'invoice_id' => $invoices[0]->id,
            'order_id' => $orders[0]->id,
            'gateway' => 'alipay',
            'amount' => 128,
            'status' => 'succeeded',
            'transaction_id' => 'ALI-DEMO-0001',
            'paid_at' => now()->subDays(12),
        ]);

        LedgerEntry::create([
            'user_id' => $customers[0]->id,
            'type' => 'payment',
            'amount' => '-128.000000',
            'balance_after' => bcsub((string) $customers[0]->balance, '128', 6),
            'reference_type' => Order::class,
            'reference_id' => $orders[0]->id,
            'description' => '订单支付 '.$orders[0]->order_no,
        ]);

        Payment::create([
            'payment_no' => 'PAY-202611-0002',
            'user_id' => $customers[1]->id,
            'invoice_id' => $invoices[1]->id,
            'order_id' => $orders[1]->id,
            'gateway' => 'alipay',
            'amount' => 178,
            'status' => 'succeeded',
            'transaction_id' => 'ALI-DEMO-0002',
            'paid_at' => now()->subDays(6),
        ]);

        LedgerEntry::create([
            'user_id' => $customers[1]->id,
            'type' => 'payment',
            'amount' => '-178.000000',
            'balance_after' => bcsub((string) $customers[1]->balance, '178', 6),
            'reference_type' => Order::class,
            'reference_id' => $orders[1]->id,
            'description' => '订单支付 '.$orders[1]->order_no,
        ]);

        $ticket = Ticket::create([
            'ticket_no' => 'TKT-202611-0001',
            'user_id' => $customers[0]->id,
            'department' => 'technical',
            'subject' => 'NAT 端口 20002 无法访问',
            'status' => 'open',
            'priority' => 'high',
            'assigned_admin_id' => $superAdmin->id,
            'last_reply_at' => now()->subMinutes(20),
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $customers[0]->id,
            'body' => '你好，今天下午开始 20002 端口的 SSH 连不上，麻烦帮忙看下。',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'admin_id' => $superAdmin->id,
            'body' => '收到，我们正在检查宿主机 nftables 规则和端口占用，稍后同步。',
        ]);

        Ticket::create([
            'ticket_no' => 'TKT-202611-0002',
            'user_id' => $customers[1]->id,
            'department' => 'billing',
            'subject' => '续费账单金额与预期不一致',
            'status' => 'pending',
            'priority' => 'normal',
            'assigned_admin_id' => Admin::query()->where('email', 'finance@deviy.cn')->value('id'),
            'last_reply_at' => now()->subHours(2),
        ]);

        Ticket::create([
            'ticket_no' => 'TKT-202611-0003',
            'user_id' => $customers[2]->id,
            'department' => 'technical',
            'subject' => '日本 NAT 开发机一直显示开通中',
            'status' => 'open',
            'priority' => 'urgent',
            'assigned_admin_id' => $superAdmin->id,
            'last_reply_at' => now()->subMinutes(48),
        ]);

        Announcement::create([
            'title' => '香港节点网络升级维护通知',
            'slug' => 'hk-network-maintenance-2026-11',
            'type' => 'announcement',
            'summary' => '11 月 8 日 02:00–05:00 对香港 BGP 线路进行升级，期间可能出现短暂抖动。',
            'body' => '为提升香港节点网络质量，我们将于 11 月 8 日凌晨进行 BGP 线路升级。升级期间可能出现 1–3 分钟短暂抖动，请提前做好业务容灾。',
            'is_published' => true,
            'published_at' => now()->subDays(2),
        ]);

        Announcement::create([
            'title' => 'NAT 云机端口段管理功能上线',
            'slug' => 'nat-port-management-release',
            'type' => 'news',
            'summary' => '现在可以在控制台自助添加端口映射，并实时查看带宽与连接数。',
            'body' => 'NAT 云机控制台新增端口映射、带宽曲线和防火墙规则预览，欢迎体验。',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        foreach ([
            ['支付宝', 'alipay', 'payment', '1.0.0', '支付宝电脑网站支付 / 手机支付', true],
            ['微信支付', 'wechat', 'payment', '1.0.0', '微信 Native / H5 支付', false],
            ['魔方财务', 'mofang', 'upstream', '0.1.0', '同步上游商品、加价上架与代购履约', true],
            ['短信通知', 'sms', 'notification', '1.0.0', '订单、到期与工单短信通知', false],
            ['邮件通知', 'mail', 'notification', '1.0.0', 'SMTP 邮件模板与通知', true],
        ] as [$name, $code, $type, $version, $description, $enabled]) {
            Plugin::create(compact('name', 'code', 'type', 'version', 'description') + [
                'is_enabled' => $enabled,
                'config' => [],
            ]);
        }

        foreach ([
            ['MF-HK-CN2-2C4G', '香港 CN2 · 2C4G', '云服务器', '香港', ['monthly' => 88, 'quarterly' => 248, 'annually' => 888]],
            ['MF-JP-NAT-1C1G', '日本 NAT · 1C1G', 'NAT 云机', '日本', ['monthly' => 25, 'quarterly' => 69, 'annually' => 249]],
            ['MF-US-HIGH-DEFENSE', '美国高防 · 4C8G', '高防云服务器', '美国', ['monthly' => 268, 'quarterly' => 768, 'annually' => 2680]],
        ] as [$upstreamId, $name, $group, $region, $prices]) {
            UpstreamProduct::create([
                'source' => 'mofang',
                'upstream_id' => $upstreamId,
                'name' => $name,
                'group_name' => $group,
                'region' => $region,
                'description' => '来自魔方财务上游的演示商品，导入后可加价上架。',
                'prices' => $prices,
                'currency' => 'CNY',
                'status' => 'available',
                'raw_payload' => compact('upstreamId', 'name', 'group', 'region', 'prices'),
                'synced_at' => now(),
            ]);
        }

        foreach ([
            ['site', 'site_name', 'Deviy Cloud', 'string'],
            ['site', 'site_url', 'https://www.deviy.cn', 'string'],
            ['site', 'site_slogan', '稳定、透明、可长期依赖的云服务', 'string'],
            ['site', 'support_email', 'support@deviy.cn', 'string'],
            ['site', 'support_qq', '854124946', 'string'],
            ['billing', 'currency', 'CNY', 'string'],
            ['billing', 'invoice_prefix', 'INV', 'string'],
            ['billing', 'order_prefix', 'ORD', 'string'],
            ['billing', 'auto_suspend_days', '7', 'integer'],
            ['attachment', 'quota_standard_mb', '50', 'integer'],
            ['attachment', 'quota_vip_mb', '200', 'integer'],
        ] as [$group, $key, $value, $type]) {
            Setting::create([
                'group_name' => $group,
                'key' => $key,
                'value' => $value,
                'type' => $type,
            ]);
        }

        $dedicatedPool = IpPool::create([
            'node_id' => $nodes[0]->id,
            'name' => '香港独立 IP',
            'cidr' => '203.0.113.0/24',
            'type' => 'dedicated',
            'gateway' => '203.0.113.1',
            'total' => 10,
            'used' => 1,
        ]);

        IpAddress::create(['pool_id' => $dedicatedPool->id, 'address' => '203.0.113.21', 'status' => 'assigned', 'service_id' => $services[0]->id, 'assigned_at' => now()]);
        foreach (range(22, 30) as $lastOctet) {
            IpAddress::create(['pool_id' => $dedicatedPool->id, 'address' => '203.0.113.'.$lastOctet, 'status' => 'available']);
        }

        $natPool = IpPool::create([
            'node_id' => $nodes[0]->id,
            'name' => '香港 NAT 出口',
            'cidr' => '203.0.113.88/32',
            'type' => 'nat',
            'gateway' => '203.0.113.88',
            'total' => 10,
            'used' => 0,
        ]);
        IpAddress::create(['pool_id' => $natPool->id, 'address' => '203.0.113.88', 'status' => 'available']);

        foreach ($services as $service) {
            if ($service->nat_ip && $service->nat_port_start && $service->nat_port_end) {
                NatPortAllocation::create([
                    'service_id' => $service->id,
                    'external_ip' => $service->nat_ip,
                    'port_start' => $service->nat_port_start,
                    'port_end' => $service->nat_port_end,
                    'status' => 'allocated',
                ]);
            }
        }
    }
}
