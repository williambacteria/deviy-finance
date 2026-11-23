<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $services = $user->services()
            ->with(['product.group.parent.parent', 'node', 'natMappings'])
            ->latest()
            ->limit(5)
            ->get();

        $invoices = $user->invoices()
            ->whereIn('status', ['unpaid', 'partial'])
            ->orderBy('due_at')
            ->limit(4)
            ->get();

        $tickets = $user->tickets()
            ->latest()
            ->limit(4)
            ->get();

        $announcements = Announcement::query()
            ->published()
            ->latest('published_at')
            ->limit(3)
            ->get();

        $serviceRenewalQuotes = [];
        foreach ($services as $listedService) {
            try {
                $serviceRenewalQuotes[$listedService->id] = app(\App\Services\Billing\RenewalService::class)->quote($listedService, $listedService->billing_cycle ?: 'monthly')['amount'];
            } catch (\Illuminate\Validation\ValidationException) {
                $serviceRenewalQuotes[$listedService->id] = null;
            }
        }

        return view('console.dashboard', [
            'user' => $user,
            'services' => $services,
            'serviceRenewalQuotes' => $serviceRenewalQuotes,
            'invoices' => $invoices,
            'tickets' => $tickets,
            'announcements' => $announcements,
            'stats' => [
                'active_services' => $user->services()->where('status', 'active')->count(),
                'pending_services' => $user->services()->whereIn('status', ['pending', 'provisioning'])->count(),
                'unpaid_amount' => Money::add($invoices->sum(fn (Invoice $invoice) => (float) $invoice->amount), 0),
                'open_tickets' => $user->tickets()->whereIn('status', ['open', 'pending'])->count(),
            ],
        ]);
    }
}
