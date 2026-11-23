<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = $request->user()->invoices()
            ->with('order')
            ->latest()
            ->paginate(15);

        return view('console.invoices.index', compact('invoices'));
    }

    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        $invoice->load(['order.items', 'user']);

        return view('console.invoices.show', [
            'invoice' => $invoice,
            'settings' => Setting::query()->pluck('value', 'key'),
        ]);
    }
}
