<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Services\Platform\PlatformSettingsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, PlatformSettingsService $settings): Response
    {
        [$from, $to] = $this->dateRange($request);
        $currency = strtoupper((string) $settings->get('general', 'default_currency', 'USD'));

        $payments = Payment::query()->whereBetween('created_at', [$from, $to]);
        $invoices = Invoice::query()->whereBetween('created_at', [$from, $to]);
        $tickets = SupportTicket::query()->whereBetween('created_at', [$from, $to]);

        return Inertia::render('Admin/Reports/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'currency' => $currency,
            'stats' => [
                'newTenants' => Tenant::query()->whereBetween('created_at', [$from, $to])->count(),
                'activeSubscriptions' => Subscription::query()->where('status', 'active')->count(),
                'invoiced' => (clone $invoices)->where('currency', $currency)->where('status', '!=', 'void')->sum('total'),
                'collected' => (clone $payments)->where('currency', $currency)->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->sum('amount'),
                'refunded' => (clone $payments)->where('currency', $currency)->sum('refunded_amount'),
                'openTickets' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(),
            ],
            'subscriptionStatuses' => Subscription::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'invoiceStatuses' => (clone $invoices)
                ->selectRaw('status, currency, count(*) as total, sum(total) as amount')
                ->groupBy('status', 'currency')
                ->orderBy('status')
                ->orderBy('currency')
                ->get(),
            'paymentProviders' => (clone $payments)
                ->selectRaw('provider, currency, count(*) as total, sum(amount) as amount, sum(refunded_amount) as refunded')
                ->groupBy('provider', 'currency')
                ->orderBy('provider')
                ->orderBy('currency')
                ->get(),
            'ticketStatuses' => (clone $tickets)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'planMix' => Plan::query()
                ->withCount(['subscriptions' => fn (Builder $query) => $query->whereIn('status', ['trial', 'active', 'manual'])])
                ->orderBy('sort_order')
                ->get(['id', 'name', 'currency']),
            'recentPayments' => (clone $payments)
                ->with(['tenant:id,company_name', 'invoice:id,number'])
                ->latest()
                ->limit(8)
                ->get(),
            'recentTickets' => (clone $tickets)
                ->with(['tenant:id,company_name', 'assignee:id,name'])
                ->latest('last_activity_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $type = $request->validate([
            'type' => ['required', 'in:tenants,subscriptions,invoices,payments,tickets'],
        ])['type'];

        $filename = sprintf('%s-%s-to-%s.csv', $type, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($type, $from, $to): void {
            $output = fopen('php://output', 'wb');

            match ($type) {
                'tenants' => $this->exportTenants($output, $from, $to),
                'subscriptions' => $this->exportSubscriptions($output, $from, $to),
                'invoices' => $this->exportInvoices($output, $from, $to),
                'payments' => $this->exportPayments($output, $from, $to),
                'tickets' => $this->exportTickets($output, $from, $to),
            };

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();

        return [$from, $to];
    }

    private function exportTenants($output, Carbon $from, Carbon $to): void
    {
        fputcsv($output, ['Company', 'Slug', 'Status', 'Plan', 'Primary domain', 'Created']);

        Tenant::query()->with(['plan:id,name', 'domains'])->whereBetween('created_at', [$from, $to])->orderBy('created_at')->chunk(200, function ($rows) use ($output): void {
            foreach ($rows as $tenant) {
                fputcsv($output, [
                    $tenant->company_name,
                    $tenant->slug,
                    $tenant->status?->value ?? $tenant->status,
                    $tenant->plan?->name,
                    $tenant->domains->firstWhere('is_primary', true)?->domain ?? $tenant->domains->first()?->domain,
                    optional($tenant->created_at)->toDateTimeString(),
                ]);
            }
        });
    }

    private function exportSubscriptions($output, Carbon $from, Carbon $to): void
    {
        fputcsv($output, ['Tenant', 'Plan', 'Status', 'Billing interval', 'Starts', 'Period ends', 'Created']);

        Subscription::query()->with(['tenant:id,company_name', 'plan:id,name'])->whereBetween('created_at', [$from, $to])->orderBy('created_at')->chunk(200, function ($rows) use ($output): void {
            foreach ($rows as $subscription) {
                fputcsv($output, [
                    $subscription->tenant?->company_name,
                    $subscription->plan?->name,
                    $subscription->status?->value ?? $subscription->status,
                    $subscription->billing_interval,
                    optional($subscription->starts_at)->toDateTimeString(),
                    optional($subscription->current_period_ends_at)->toDateTimeString(),
                    optional($subscription->created_at)->toDateTimeString(),
                ]);
            }
        });
    }

    private function exportInvoices($output, Carbon $from, Carbon $to): void
    {
        fputcsv($output, ['Number', 'Tenant', 'Status', 'Subtotal', 'Tax', 'Total', 'Currency', 'Issued', 'Due']);

        Invoice::query()->with('tenant:id,company_name')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->chunk(200, function ($rows) use ($output): void {
            foreach ($rows as $invoice) {
                fputcsv($output, [
                    $invoice->number,
                    $invoice->tenant?->company_name,
                    $invoice->status,
                    $invoice->subtotal,
                    $invoice->tax_total,
                    $invoice->total,
                    $invoice->currency,
                    optional($invoice->issued_on)->toDateString(),
                    optional($invoice->due_on)->toDateString(),
                ]);
            }
        });
    }

    private function exportPayments($output, Carbon $from, Carbon $to): void
    {
        fputcsv($output, ['Tenant', 'Invoice', 'Provider', 'Reference', 'Status', 'Amount', 'Refunded', 'Currency', 'Paid at', 'Created']);

        Payment::query()->with(['tenant:id,company_name', 'invoice:id,number'])->whereBetween('created_at', [$from, $to])->orderBy('created_at')->chunk(200, function ($rows) use ($output): void {
            foreach ($rows as $payment) {
                fputcsv($output, [
                    $payment->tenant?->company_name,
                    $payment->invoice?->number,
                    $payment->provider,
                    $payment->provider_reference,
                    $payment->status,
                    $payment->amount,
                    $payment->refunded_amount,
                    $payment->currency,
                    optional($payment->paid_at)->toDateTimeString(),
                    optional($payment->created_at)->toDateTimeString(),
                ]);
            }
        });
    }

    private function exportTickets($output, Carbon $from, Carbon $to): void
    {
        fputcsv($output, ['Number', 'Tenant', 'Subject', 'Status', 'Priority', 'Category', 'Assigned to', 'SLA due', 'Created']);

        SupportTicket::query()->with(['tenant:id,company_name', 'assignee:id,name'])->whereBetween('created_at', [$from, $to])->orderBy('created_at')->chunk(200, function ($rows) use ($output): void {
            foreach ($rows as $ticket) {
                fputcsv($output, [
                    $ticket->number,
                    $ticket->tenant?->company_name,
                    $ticket->subject,
                    $ticket->status,
                    $ticket->priority,
                    $ticket->category,
                    $ticket->assignee?->name,
                    optional($ticket->sla_due_at)->toDateTimeString(),
                    optional($ticket->created_at)->toDateTimeString(),
                ]);
            }
        });
    }
}
