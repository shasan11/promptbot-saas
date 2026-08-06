<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvoiceStoreRequest;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->with('tenant')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('tenant_id')->isNotEmpty(), fn ($query) => $query->where('tenant_id', $request->string('tenant_id')))
            ->latest('issued_on')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'tenants' => Tenant::query()->orderBy('company_name')->get(['id', 'company_name']),
            'filters' => $request->only(['status', 'tenant_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Invoices/Create', [
            'tenants' => Tenant::query()->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function store(InvoiceStoreRequest $request, InvoiceService $invoices, AuditLogService $auditLog): RedirectResponse
    {
        $invoice = $invoices->create($request->validated());
        $auditLog->record('invoice.created', $invoice, [
            'tenant_id' => $invoice->tenant_id,
            'new_values' => ['number' => $invoice->number, 'total' => (string) $invoice->total, 'status' => $invoice->status],
        ]);

        return redirect()->route('superadmin.billing.invoices.show', $invoice)->with('status', 'Invoice created.');
    }

    public function show(Invoice $invoice): Response
    {
        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => $invoice->load(['tenant', 'items']),
        ]);
    }

    public function markPaid(Invoice $invoice, InvoiceService $invoices, AuditLogService $auditLog): RedirectResponse
    {
        if (in_array($invoice->status, ['paid', 'void'], true)) {
            return back()->with('error', 'This invoice is already '.$invoice->status.'.');
        }

        $invoices->markPaid($invoice);
        $auditLog->record('invoice.paid', $invoice, ['tenant_id' => $invoice->tenant_id]);

        return back()->with('status', 'Invoice marked as paid.');
    }

    public function void(Invoice $invoice, InvoiceService $invoices, AuditLogService $auditLog): RedirectResponse
    {
        if ($invoice->status === 'void') {
            return back()->with('error', 'This invoice is already void.');
        }

        $invoices->void($invoice);
        $auditLog->record('invoice.voided', $invoice, ['tenant_id' => $invoice->tenant_id, 'severity' => 'warning']);

        return back()->with('status', 'Invoice voided.');
    }
}
