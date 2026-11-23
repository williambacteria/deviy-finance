<?php

namespace App\Services\Legacy;

use App\Models\Invoice;
use App\Models\LegacyImportRecord;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use PDO;
use RuntimeException;

class LegacyFinanceMirror
{
    public function exportServices(PDO $pdo, User $user): void
    {
        $records = LegacyImportRecord::query()->where('source', 'www.deviy.cn')->where('source_table', 'shd_host')->where('target_table', 'services')->whereIn('target_id', $user->services()->pluck('id'))->get();
        $query = $pdo->prepare('UPDATE shd_host SET nextduedate=?,nextinvoicedate=? WHERE id=? AND uid=1');
        foreach ($records as $record) {
            $service = Service::find($record->target_id);
            if ($service && $service->expires_at) {
                $query->execute([$service->expires_at->timestamp, $service->expires_at->timestamp, (int) $record->source_id]);
            }
        }
    }

    public function import(PDO $pdo, User $user): void
    {
        $hosts = $pdo->query('SELECT * FROM shd_host WHERE uid=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($hosts as $host) {
            $mapping = LegacyImportRecord::query()->where('source', 'www.deviy.cn')->where('source_table', 'shd_host')->where('source_id', (string) $host['id'])->first();
            $existing = $mapping?->target_id ? Service::withTrashed()->find($mapping->target_id) : null;
            $status = match ($host['domainstatus']) { 'Active' => 'active', 'Suspended' => 'suspended', 'Pending' => 'pending', default => 'terminated' };
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) {
                    throw new RuntimeException('Legacy resource ownership mismatch.');
                }
                $specs = (array) $existing->specs;
                $legacy = (array) ($specs['legacy_import'] ?? []);
                $legacy['source'] = 'www.deviy.cn';
                $legacy['old_host_id'] = (int) $host['id'];
                $legacy['renewal_prices'] = [$host['billingcycle'] => (string) $host['amount']];
                $legacy['original_amount'] = (string) $host['amount'];
                $specs['legacy_import'] = $legacy;
                $updates = ['billing_cycle' => $host['billingcycle'], 'specs' => $specs];
                if (($mapping->payload['domainstatus'] ?? null) !== $host['domainstatus']) {
                    $updates['status'] = $status;
                    $payload = $mapping->payload;
                    $payload['domainstatus'] = $host['domainstatus'];
                    $mapping->update(['payload' => $payload]);
                }
                if ((int) $host['nextduedate'] > 0) {
                    $updates['expires_at'] = Carbon::createFromTimestamp((int) $host['nextduedate']);
                    $updates['next_due_at'] = $updates['expires_at']->copy()->subDays(7);
                }
                $existing->update($updates);
                if (! $existing->external_ref && $product = $existing->product) {
                    if ($product->upstream_source === 'qzvps') {
                        $query = $pdo->prepare("SELECT v.value FROM shd_customfieldsvalues v JOIN shd_customfields f ON f.id=v.fieldid WHERE v.relid=? AND f.type='product' AND f.fieldname='vserverid'");
                        $query->execute([$host['id']]);
                        $resourceId = (int) $query->fetchColumn();
                        if ($resourceId > 0) {
                            $existing->update(['external_ref' => 'qzvps:'.$resourceId, 'provision_status' => 'succeeded']);
                        }
                    }
                }
                continue;
            }
            $productMap = LegacyImportRecord::query()->where('source', 'www.deviy.cn')->where('source_table', 'shd_products')->where('source_id', (string) $host['productid'])->firstOrFail();
            $product = Product::findOrFail($productMap->target_id);
            $order = $this->order($pdo, (int) $host['orderid'], $user);
            $specs = (array) data_get($product->metadata, 'specs', []);
            $query = $pdo->prepare('SELECT c.option_name,s.option_name selected_name,o.qty FROM shd_host_config_options o JOIN shd_product_config_options c ON c.id=o.configid JOIN shd_product_config_options_sub s ON s.id=o.optionid WHERE o.relid=?');
            $query->execute([$host['id']]);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $option) {
                $key = explode('|', $option['option_name'])[0];
                $value = explode('|', $option['selected_name'])[0];
                $target = ['cpu' => 'cpu', 'memory' => 'memory_gb', 'bw' => 'upload_mbps', 'data_size' => 'data_disk_gb', 'data_disk_size' => 'data_disk_gb', 'os' => 'ostype'][$key] ?? null;
                if ($target) {
                    $specs[$target] = $target === 'ostype' ? $value : (int) $value;
                }
            }
            $specs['legacy_import'] = ['source' => 'www.deviy.cn', 'old_host_id' => (int) $host['id'], 'old_product_id' => (int) $host['productid'], 'original_cycle' => $host['billingcycle'], 'original_amount' => (string) $host['amount'], 'renewal_prices' => [$host['billingcycle'] => (string) $host['amount']], 'api_created' => true];
            $query = $pdo->prepare("SELECT v.value FROM shd_customfieldsvalues v JOIN shd_customfields f ON f.id=v.fieldid WHERE v.relid=? AND f.type='product' AND f.fieldname='vserverid'");
            $query->execute([$host['id']]);
            $ref = (int) $query->fetchColumn();
            $service = Service::create(['service_no' => 'LEG-WWW-SRV-'.$host['id'], 'user_id' => $user->id, 'product_id' => $product->id, 'order_id' => $order?->id, 'name' => trim((string) $host['domain']) ?: $product->name, 'status' => $status, 'billing_cycle' => $host['billingcycle'], 'auto_renew' => false, 'region' => $product->region, 'ip_address' => filter_var($host['dedicatedip'], FILTER_VALIDATE_IP) ? $host['dedicatedip'] : null, 'os' => $specs['ostype'] ?? $host['os'], 'specs' => $specs, 'expires_at' => (int) $host['nextduedate'] > 0 ? Carbon::createFromTimestamp((int) $host['nextduedate']) : null, 'provision_status' => $status === 'pending' ? 'legacy_api_pending' : 'succeeded', 'external_ref' => $ref > 0 ? 'qzvps:'.$ref : null]);
            if ($service->expires_at) {
                $service->update(['next_due_at' => $service->expires_at->copy()->subDays(7)]);
            }
            if ($mapping) {
                $mapping->update(['target_table' => 'services', 'target_id' => $service->id, 'state' => 'api_imported']);
            } else {
                LegacyImportRecord::create(['source' => 'www.deviy.cn', 'source_table' => 'shd_host', 'source_id' => (string) $host['id'], 'target_table' => 'services', 'target_id' => $service->id, 'state' => 'api_imported', 'payload' => $host]);
            }
            if ($order) {
                OrderItem::firstOrCreate(['order_id' => $order->id, 'service_id' => $service->id], ['product_id' => $product->id, 'product_name' => $product->name, 'billing_cycle' => $host['billingcycle'], 'quantity' => 1, 'unit_price' => $host['firstpaymentamount'], 'amount' => $host['firstpaymentamount']]);
            }
        }
        $orders = $pdo->query('SELECT id FROM shd_orders WHERE uid=1')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($orders as $id) {
            $this->order($pdo, (int) $id, $user);
        }
    }

    private function order(PDO $pdo, int $id, User $user): ?Order
    {
        if ($id < 1) {
            return null;
        }
        $query = $pdo->prepare('SELECT * FROM shd_orders WHERE id=? AND uid=1');
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            return null;
        }
        $mapping = LegacyImportRecord::query()->where('source', 'www.deviy.cn')->where('source_table', 'shd_orders')->where('source_id', (string) $id)->first();
        if ($mapping?->target_id) {
            $existing = Order::find($mapping->target_id);
            if ($existing && $mapping->state === 'api_imported') {
                $existing->update(['status' => $row['status'] === 'Active' ? 'paid' : ($row['status'] === 'Cancelled' ? 'cancelled' : 'pending'), 'paid_at' => (int) $row['pay_time'] > 0 ? Carbon::createFromTimestamp((int) $row['pay_time']) : null]);
                $existing->invoices()->update(['status' => $row['status'] === 'Active' ? 'paid' : 'unpaid', 'paid_amount' => $row['status'] === 'Active' ? $row['amount'] : 0]);
            }

            return $existing;
        }
        $order = Order::firstOrCreate(['order_no' => 'LEG-WWW-ORD-'.$id], ['user_id' => $user->id, 'type' => 'legacy_history', 'status' => $row['status'] === 'Active' ? 'paid' : ($row['status'] === 'Cancelled' ? 'cancelled' : 'pending'), 'subtotal' => $row['amount'], 'total' => $row['amount'], 'discount' => 0, 'tax' => 0, 'currency' => 'CNY', 'paid_at' => (int) $row['pay_time'] > 0 ? Carbon::createFromTimestamp((int) $row['pay_time']) : null]);
        LegacyImportRecord::updateOrCreate(['source' => 'www.deviy.cn', 'source_table' => 'shd_orders', 'source_id' => (string) $id], ['target_table' => 'orders', 'target_id' => $order->id, 'state' => 'api_imported', 'payload' => $row]);
        if ((int) $row['invoiceid'] > 0) {
            $query = $pdo->prepare('SELECT * FROM shd_invoices WHERE id=? AND uid=1');
            $query->execute([$row['invoiceid']]);
            $invoice = $query->fetch(PDO::FETCH_ASSOC);
            if ($invoice) {
                $target = Invoice::firstOrCreate(['invoice_no' => 'LEG-WWW-INV-'.$invoice['id']], ['user_id' => $user->id, 'order_id' => $order->id, 'status' => $invoice['status'] === 'Paid' ? 'paid' : 'unpaid', 'amount' => $invoice['total'], 'paid_amount' => $invoice['status'] === 'Paid' ? $invoice['total'] : 0, 'due_at' => (int) $invoice['due_time'] > 0 ? Carbon::createFromTimestamp((int) $invoice['due_time']) : null, 'paid_at' => (int) $invoice['paid_time'] > 0 ? Carbon::createFromTimestamp((int) $invoice['paid_time']) : null, 'items' => [['name' => '下游 API 订单', 'amount' => $invoice['total']]]]);
                LegacyImportRecord::updateOrCreate(['source' => 'www.deviy.cn', 'source_table' => 'shd_invoices', 'source_id' => (string) $invoice['id']], ['target_table' => 'invoices', 'target_id' => $target->id, 'state' => 'api_imported', 'payload' => $invoice]);
            }
        }

        return $order;
    }
}
