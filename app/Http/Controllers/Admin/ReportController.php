<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\ReconciliationRun;
use App\Services\Finance\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReconciliationService $reconciliation): View
    {
        [$from, $to] = $this->range($request);
        $paidStatuses = ['paid', 'partially_refunded', 'refunded'];

        $orders = Order::query()
            ->with('user')
            ->whereIn('status', $paidStatuses)
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $stats = [
            'orders' => (clone $orders)->count(),
            'gross' => (float) (clone $orders)->selectRaw('COALESCE(SUM(total), 0) as value')->value('value'),
            'refunds' => (float) (clone $orders)->selectRaw('COALESCE(SUM(refunded_amount), 0) as value')->value('value'),
            'net' => (float) (clone $orders)->selectRaw('COALESCE(SUM(total - refunded_amount), 0) as value')->value('value'),
            'ledger' => (float) LedgerEntry::query()
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->sum('amount'),
        ];

        $daily = (clone $orders)
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as orders, SUM(total) as gross, SUM(refunded_amount) as refunds, SUM(total - refunded_amount) as net')
            ->groupBy('day')
            ->orderByDesc('day')
            ->get();

        $check = $reconciliation->check($from, $to);

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'stats' => $stats,
            'daily' => $daily,
            'reconciliation' => $check,
            'reconciliationRuns' => ReconciliationRun::query()->latest('run_date')->limit(10)->get(),
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['orders', 'ledger', 'summary'], true), 404);

        [$from, $to] = $this->range($request);
        $paidStatuses = ['paid', 'partially_refunded', 'refunded'];
        $filename = 'deviy-'.$type.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($type, $from, $to, $paidStatuses): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            if ($type === 'ledger') {
                fputcsv($handle, ['时间', '客户', '类型', '金额', '余额', '说明']);
                LedgerEntry::query()
                    ->with('user')
                    ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orderBy('id')
                    ->chunk(500, function ($entries) use ($handle): void {
                        foreach ($entries as $entry) {
                            fputcsv($handle, [
                                $entry->created_at?->format('Y-m-d H:i:s'),
                                $entry->user?->name,
                                $entry->type,
                                $entry->amount,
                                $entry->balance_after,
                                $entry->description,
                            ]);
                        }
                    });
            } elseif ($type === 'orders') {
                fputcsv($handle, ['订单号', '客户', '状态', '订单金额', '已退款', '净收入', '支付时间']);
                Order::query()
                    ->with('user')
                    ->whereIn('status', $paidStatuses)
                    ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orderBy('id')
                    ->chunk(500, function ($orders) use ($handle): void {
                        foreach ($orders as $order) {
                            fputcsv($handle, [
                                $order->order_no,
                                $order->user?->name,
                                $order->status,
                                $order->total,
                                $order->refunded_amount,
                                bcsub((string) $order->total, (string) $order->refunded_amount, 6),
                                $order->paid_at?->format('Y-m-d H:i:s'),
                            ]);
                        }
                    });
            } else {
                fputcsv($handle, ['日期', '订单数', '订单金额', '退款', '净收入']);
                Order::query()
                    ->whereIn('status', $paidStatuses)
                    ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->selectRaw('DATE(paid_at) as day, COUNT(*) as orders, SUM(total) as gross, SUM(refunded_amount) as refunds, SUM(total - refunded_amount) as net')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->chunk(500, function ($rows) use ($handle): void {
                        foreach ($rows as $row) {
                            fputcsv($handle, [$row->day, $row->orders, $row->gross, $row->refunds, $row->net]);
                        }
                    });
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from']) : now()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : now();

        return [$from, $to];
    }
}
