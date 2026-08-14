<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountNote;
use App\Models\CustomerAccountLimit;
use App\Models\PortalUser;
use App\Enums\PortalUserStatus;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\CustomerPortalService;
use App\Services\Platform\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;

class CustomerAccountController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Customers/Accounts/Create', [
            'defaults' => ['currency' => config('platform.default_currency', 'USD'), 'timezone' => config('app.timezone', 'UTC'), 'billing_mode' => 'per_service'],
        ]);
    }

    public function edit(CustomerAccount $account): Response
    {
        return Inertia::render('Admin/Customers/Accounts/Create', ['account' => $account]);
    }

    public function update(Request $request, CustomerAccount $account, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'legal_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'], 'default_currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'], 'billing_mode' => ['required', 'in:per_service,consolidated'],
        ]);
        $data['default_currency'] = strtoupper($data['default_currency']);
        $old = $account->only(array_keys($data));
        $account->update($data);
        $audit->record('customer_account.updated', $account, ['old_values' => $old, 'new_values' => $data]);
        return redirect()->route('superadmin.customers.accounts.show', $account)->with('status', 'Customer account updated.');
    }

    public function store(Request $request, CustomerAccountService $accounts, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'legal_name' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'], 'owner_email' => ['required', 'email', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'], 'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'], 'billing_mode' => ['required', 'in:per_service,consolidated'],
        ]);
        $existing = PortalUser::where('email', strtolower($data['owner_email']))->first();
        $owner = $existing ?: PortalUser::create([
            'name' => $data['owner_name'], 'email' => strtolower($data['owner_email']),
            'password' => Str::password(32), 'status' => PortalUserStatus::Active, 'timezone' => $data['timezone'],
        ]);
        $account = $accounts->createWithOwner($owner, collect($data)->only(['name', 'legal_name', 'billing_email', 'currency', 'timezone', 'billing_mode'])->all());
        if (! $existing) PasswordBroker::broker('portal_users')->sendResetLink(['email' => $owner->email]);
        $audit->record('customer_account.created', $account, ['new_values' => ['name' => $account->name, 'owner_email' => $owner->email]]);
        return redirect()->route('superadmin.customers.accounts.show', $account)->with('status', $existing ? 'Customer account created.' : 'Customer account created and password setup email sent.');
    }

    public function index(Request $request, CustomerPortalService $portal): Response
    {
        $accounts = CustomerAccount::query()
            ->with(['owner:id,public_uuid,name,email', 'subscriptions.plan'])
            ->withCount(['tenants', 'users'])
            ->withSum(['invoices as outstanding' => fn ($query) => $query->whereIn('status', ['open', 'past_due'])], 'total')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $search)
                    ->orWhere('account_number', 'like', $search)->orWhere('billing_email', 'like', $search)
                    ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', $search)->orWhere('email', 'like', $search))
                    ->orWhereHas('tenants', fn ($tenant) => $tenant->where('company_name', 'like', $search)->orWhere('slug', 'like', $search)));
            })->latest()->paginate(20)->withQueryString();

        $accounts->getCollection()->transform(function (CustomerAccount $account) use ($portal): CustomerAccount {
            $account->setAttribute('mrr', round($account->subscriptions->filter(fn ($subscription) => in_array($subscription->status->value, ['active', 'trial', 'past_due'], true))->sum(fn ($subscription) => $portal->monthlyValue($subscription)), 2));
            unset($account->subscriptions);
            return $account;
        });

        return Inertia::render('Admin/Customers/Accounts/Index', ['accounts' => $accounts, 'filters' => $request->only(['search', 'status'])]);
    }

    public function show(CustomerAccount $account, CustomerPortalService $portal): Response
    {
        $account->load([
            'owner:id,public_uuid,name,email,phone', 'billingProfile',
            'tenants' => fn ($query) => $query->with(['plan', 'domains', 'subscriptions.plan'])->latest(),
            'users' => fn ($query) => $query->orderBy('name'),
            'subscriptions' => fn ($query) => $query->with(['plan', 'tenant'])->latest(),
            'invoices' => fn ($query) => $query->latest('issued_on')->limit(20),
            'payments' => fn ($query) => $query->latest()->limit(20),
            'supportTickets' => fn ($query) => $query->latest('last_activity_at')->limit(20),
            'activities' => fn ($query) => $query->limit(30), 'limits' => fn ($query) => $query->orderBy('feature_key'),
        ])->loadCount(['tenants', 'users', 'supportTickets as open_support_tickets_count' => fn ($query) => $query->whereNotIn('status', ['closed', 'resolved'])]);
        $account->setAttribute('mrr', round($account->subscriptions->filter(fn ($subscription) => in_array($subscription->status->value, ['active', 'trial', 'past_due'], true))->sum(fn ($subscription) => $portal->monthlyValue($subscription)), 2));
        $account->setAttribute('outstanding', (float) $account->invoices()->whereIn('status', ['open', 'past_due'])->sum('total'));
        $account->setAttribute('total_paid', (float) $account->payments()->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->sum('amount'));

        return Inertia::render('Admin/Customers/Accounts/Show', [
            'account' => $account,
            'notes' => CustomerAccountNote::where('customer_account_id', $account->id)->with('account:id,name')->latest()->get(),
        ]);
    }

    public function status(Request $request, CustomerAccount $account, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,trial,past_due,suspended,closed'], 'reason' => ['required', 'string', 'max:1000']]);
        if ($account->isSystemDefault() && $data['status'] !== 'active') {
            return back()->with('error', 'The system Default Account must remain active and cannot be closed or suspended.');
        }
        $old = $account->status->value;
        $account->update([
            'status' => $data['status'],
            'suspended_at' => $data['status'] === 'suspended' ? now() : null,
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);
        $audit->record('customer_account.status_changed', $account, ['old_values' => ['status' => $old], 'new_values' => ['status' => $data['status']], 'reason' => $data['reason']]);
        return back()->with('status', 'Customer account status updated.');
    }

    public function note(Request $request, CustomerAccount $account, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:sales,billing,support,internal'], 'body' => ['required', 'string', 'max:10000']]);
        $note = CustomerAccountNote::create([...$data, 'customer_account_id' => $account->id, 'central_user_id' => $request->user('central')->id]);
        $audit->record('customer_account.note_added', $account, ['new_values' => ['note_id' => $note->id, 'type' => $note->type]]);
        return back()->with('status', 'Internal note added.');
    }

    public function storeLimit(Request $request, CustomerAccount $account, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'feature_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'limit_value' => ['required', 'numeric', 'min:0.01'], 'unit' => ['required', 'in:count,MB,tokens,requests'],
            'period' => ['nullable', 'in:day,month,year'], 'is_enforced' => ['required', 'boolean'],
        ]);
        $limit = $account->limits()->updateOrCreate(
            ['feature_key' => $data['feature_key'], 'period' => $data['period'] ?? null],
            [...$data, 'scope' => 'account'],
        );
        $audit->record('customer_account.limit_updated', $account, ['new_values' => $limit->only(['feature_key', 'limit_value', 'unit', 'period', 'is_enforced'])]);
        return back()->with('status', 'Account-level limit saved.');
    }

    public function destroyLimit(Request $request, CustomerAccount $account, CustomerAccountLimit $limit, AuditLogService $audit): RedirectResponse
    {
        abort_unless((int) $limit->customer_account_id === (int) $account->getKey(), 404);
        $snapshot = $limit->only(['feature_key', 'limit_value', 'unit', 'period']);
        $limit->delete();
        $audit->record('customer_account.limit_removed', $account, ['old_values' => $snapshot, 'severity' => 'warning']);
        return back()->with('status', 'Account-level limit removed.');
    }
}
