<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\Invoice;
use App\Models\PortalUser;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = trim($request->string('q')->toString());
        abort_if(mb_strlen($query) > 255, 422);
        $like = '%'.addcslashes($query, '%_\\').'%';
        $user = $request->user('central');
        $results = collect();

        if ($query !== '' && $user->can('customers.view')) {
            CustomerAccount::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('account_number', 'like', $like)->orWhere('billing_email', 'like', $like))->limit(10)->get()
                ->each(fn ($item) => $results->push(['type' => 'Customer account', 'title' => $item->name, 'subtitle' => $item->account_number, 'url' => route('superadmin.customers.accounts.show', $item)]));
            PortalUser::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))->limit(10)->get()
                ->each(fn ($item) => $results->push(['type' => 'Portal user', 'title' => $item->name, 'subtitle' => $item->email, 'url' => route('superadmin.customers.users.index', ['search' => $item->email])]));
        }
        if ($query !== '' && $user->can('tenants.view')) {
            Tenant::where(fn ($q) => $q->where('company_name', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('id', 'like', $like))->limit(10)->get()
                ->each(fn ($item) => $results->push(['type' => 'Service', 'title' => $item->company_name, 'subtitle' => $item->slug, 'url' => route('superadmin.services.show', $item)]));
        }
        if ($query !== '' && $user->can('invoices.view')) {
            Invoice::where('number', 'like', $like)->limit(10)->get()
                ->each(fn ($item) => $results->push(['type' => 'Invoice', 'title' => $item->number, 'subtitle' => $item->currency.' '.$item->total, 'url' => route('superadmin.billing.invoices.show', $item)]));
        }
        if ($query !== '' && $user->can('payments.view')) {
            Payment::with('customerAccount:id,name')->where(fn ($q) => $q->where('provider_reference', 'like', $like)
                ->orWhereHas('invoice', fn ($invoice) => $invoice->where('number', 'like', $like))
                ->orWhereHas('customerAccount', fn ($account) => $account->where('name', 'like', $like)))->limit(10)->get()
                ->each(fn ($item) => $results->push(['type' => 'Payment', 'title' => $item->provider_reference ?: $item->getKey(), 'subtitle' => ($item->customerAccount?->name ? $item->customerAccount->name.' · ' : '').$item->currency.' '.$item->amount, 'url' => route('superadmin.billing.payments.show', $item)]));
        }
        if ($query !== '' && $user->can('subscriptions.view')) {
            Subscription::with(['tenant', 'plan'])->where(fn ($q) => $q->where('public_uuid', 'like', $like)->orWhere('external_id', 'like', $like))->limit(10)->get()
                ->each(fn ($item) => $results->push(['type' => 'Subscription', 'title' => $item->tenant?->company_name ?? $item->public_uuid, 'subtitle' => $item->plan?->name, 'url' => route('superadmin.subscriptions.show', $item)]));
        }
        if ($query !== '' && $user->can('support.view')) {
            SupportTicket::where(fn ($q) => $q->where('number', 'like', $like)->orWhere('subject', 'like', $like)->orWhere('requester_email', 'like', $like))->limit(10)->get()
                ->each(fn ($item) => $results->push(['type' => 'Support ticket', 'title' => $item->number.' · '.$item->subject, 'subtitle' => $item->requester_email, 'url' => route('superadmin.tickets.show', $item)]));
        }

        return Inertia::render('Admin/Search/Index', ['query' => $query, 'results' => $results->take(50)->values()]);
    }
}
