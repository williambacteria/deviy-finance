<?php

namespace App\Services\Finance;

use App\Models\LedgerEntry;
use App\Models\Order;
use Illuminate\Support\Carbon;

class ReconciliationService
{
    /**
     * @return array{checked: int, issues: array<int, array<string, string>>}
     */
    public function check(?Carbon $from = null, ?Carbon $to = null): array
    {
        $orders = Order::query()
            ->with(['user', 'payments', 'invoices'])
            ->where('type', '!=', 'legacy_history')
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->when($from, fn ($query) => $query->where('paid_at', '>=', $from->startOfDay()))
            ->when($to, fn ($query) => $query->where('paid_at', '<=', $to->endOfDay()))
            ->get();

        $ledger = LedgerEntry::query()
            ->where('reference_type', Order::class)
            ->whereIn('reference_id', $orders->pluck('id'))
            ->selectRaw('reference_id, SUM(amount) as total')
            ->groupBy('reference_id')
            ->pluck('total', 'reference_id');

        $issues = [];

        foreach ($orders as $order) {
            $paymentSum = '0';
            $balancePaid = '0';
            foreach ($order->payments->where('status', 'succeeded') as $payment) {
                if (data_get($payment->payload,'settlement') === 'wallet_credit') { continue; }
                $paymentSum = bcadd($paymentSum, (string) $payment->amount, 6);
                if ($payment->gateway === 'balance') { $balancePaid=bcadd($balancePaid,(string)$payment->amount,6); }
            }

            $invoicePaid = '0';
            foreach ($order->invoices as $invoice) {
                $invoicePaid = bcadd($invoicePaid, (string) $invoice->paid_amount, 6);
            }

            $expectedLedger = bcsub((string) $order->refunded_amount, $balancePaid, 6);
            $ledgerNet = (string) ($ledger[$order->id] ?? '0');

            if (bccomp($paymentSum, (string) $order->total, 6) !== 0) {
                $issues[] = $this->issue($order, '支付单金额不一致', (string) $order->total, $paymentSum);
            }

            if ($order->invoices->isEmpty() || bccomp($invoicePaid, (string) $order->total, 6) !== 0) {
                $issues[] = $this->issue($order, '账单已付金额不一致', (string) $order->total, $invoicePaid);
            }

            if (bccomp($ledgerNet, $expectedLedger, 6) !== 0) {
                $issues[] = $this->issue($order, '余额流水净额不一致', $expectedLedger, $ledgerNet);
            }
        }

        return ['checked' => $orders->count(), 'issues' => $issues];
    }

    /**
     * @return array<string, string>
     */
    private function issue(Order $order, string $type, string $expected, string $actual): array
    {
        return [
            'order_no' => (string) $order->order_no,
            'customer' => (string) ($order->user?->name ?? ''),
            'type' => $type,
            'expected' => $expected,
            'actual' => $actual,
        ];
    }
}
