<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use App\Models\CustomerAccount;
use App\Enums\PortalUserStatus;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PortalUserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PortalUser::class);
        $users = PortalUser::query()->withCount('accounts')->with('accounts:id,public_uuid,name')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Customers/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status']),
            'accounts' => CustomerAccount::query()->whereNotIn('status', ['closed'])->orderBy('name')->get(['id', 'public_uuid', 'name', 'account_number']),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $this->authorize('create', PortalUser::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:portal_users,email'],
            'password' => ['nullable', 'string', 'min:12', 'max:255'],
            'status' => ['required', Rule::enum(PortalUserStatus::class)],
            'timezone' => ['nullable', 'timezone'],
            'account_id' => ['nullable', 'integer', 'exists:customer_accounts,id'],
            'role' => ['nullable', 'required_with:account_id', 'in:admin,billing,member'],
        ]);

        $user = PortalUser::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => filled($data['password'] ?? null) ? $data['password'] : Str::password(32),
            'status' => $data['status'],
            'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
        ]);

        if (filled($data['account_id'] ?? null)) {
            $role = $data['role'];
            $user->accounts()->attach($data['account_id'], [
                'role' => $role,
                'can_manage_services' => $role === 'admin',
                'can_manage_billing' => in_array($role, ['admin', 'billing'], true),
                'can_manage_members' => $role === 'admin',
                'can_manage_support' => true,
                'service_access' => 'all',
                'joined_at' => $data['status'] === PortalUserStatus::Active->value ? now() : null,
            ]);
        }

        if (blank($data['password'] ?? null)) {
            PasswordBroker::broker('portal_users')->sendResetLink(['email' => $user->email]);
        }

        $audit->record('portal_user.created', $user, ['new_values' => ['email' => $user->email, 'status' => $user->status->value, 'account_id' => $data['account_id'] ?? null]]);

        return back()->with('status', blank($data['password'] ?? null) ? 'Portal user created and password setup email sent.' : 'Portal user created.');
    }
}
