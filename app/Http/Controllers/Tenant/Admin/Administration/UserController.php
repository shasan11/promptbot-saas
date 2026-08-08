<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Enums\Tenant\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Administration\UserStoreRequest;
use App\Http\Requests\Tenant\Administration\UserUpdateRequest;
use App\Models\Department;
use App\Models\Team;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $auditLog) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles:id,name,label', 'department:id,name', 'teams:id,name'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('role')->isNotEmpty(), fn ($query) => $query->whereHas('roles', fn ($r) => $r->where('roles.id', $request->integer('role'))))
            ->when($request->string('department')->isNotEmpty(), fn ($query) => $query->where('department_id', $request->integer('department')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tenant/Admin/Administration/Users/Index', [
            'users' => $users,
            'roles' => TenantRole::query()->orderBy('name')->get(['id', 'name', 'label']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'role', 'department']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Tenant/Admin/Administration/Users/Create', [
            'roles' => TenantRole::query()->orderBy('name')->get(['id', 'name', 'label']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'locale' => $data['locale'] ?? null,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'created_by' => $request->user('tenant')?->id,
            ]);

            if (! empty($data['roles'])) {
                $user->syncRoles(TenantRole::query()->whereIn('id', $data['roles'])->get());
            }

            if (! empty($data['team_ids'])) {
                $user->teams()->sync($data['team_ids']);
            }

            return $user;
        });

        $this->auditLog->record(
            event: 'user.created',
            actor: $request->user('tenant'),
            description: "Created user \"{$user->name}\"",
            subject: $user,
            newValues: ['name' => $user->name, 'email' => $user->email],
        );

        return redirect()->route('tenant.admin.administration.users.show', $user)->with('status', 'User created.');
    }

    public function show(User $user): Response
    {
        Gate::authorize('view', $user);

        $user->load(['roles', 'department', 'teams', 'suspendedBy:id,name', 'createdBy:id,name']);

        return Inertia::render('Tenant/Admin/Administration/Users/Show', [
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'allRoles' => TenantRole::query()->orderBy('name')->get(['id', 'name', 'label']),
            'allTeams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'canManageRoles' => Gate::allows('manageRoles', $user),
            'canSuspend' => Gate::allows('suspend', $user),
            'canDelete' => Gate::allows('delete', $user),
        ]);
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('Tenant/Admin/Administration/Users/Edit', [
            'user' => $user,
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $oldValues = $user->only(array_keys($request->validated()));
        $user->update($request->validated());

        $this->auditLog->record(
            event: 'user.updated',
            actor: $request->user('tenant'),
            description: "Updated user \"{$user->name}\"",
            subject: $user,
            oldValues: $oldValues,
            newValues: $request->validated(),
        );

        return redirect()->route('tenant.admin.administration.users.show', $user)->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $name = $user->name;
        $user->delete();

        $this->auditLog->record(
            event: 'user.deleted',
            actor: $request->user('tenant'),
            description: "Deleted user \"{$name}\"",
            subjectType: User::class,
            subjectLabel: $name,
        );

        return redirect()->route('tenant.admin.administration.users.index')->with('status', 'User deleted.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        abort_unless($user->status->canTransitionTo(UserStatus::Active), 422, 'Invalid status transition.');

        $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null, 'deactivated_at' => null])->save();

        $this->auditLog->record('user.activated', $request->user('tenant'), "Activated user \"{$user->name}\"", $user);

        return back()->with('status', 'User activated.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('suspend', $user);
        abort_unless($user->status->canTransitionTo(UserStatus::Suspended), 422, 'Invalid status transition.');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $user->forceFill([
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspended_by' => $request->user('tenant')?->id,
            'suspension_reason' => $data['reason'] ?? null,
        ])->save();

        $this->auditLog->record('user.suspended', $request->user('tenant'), "Suspended user \"{$user->name}\"", $user, newValues: ['reason' => $data['reason'] ?? null]);

        return back()->with('status', 'User suspended.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('suspend', $user);
        abort_unless($user->status->canTransitionTo(UserStatus::Deactivated), 422, 'Invalid status transition.');

        $user->forceFill(['status' => UserStatus::Deactivated, 'deactivated_at' => now()])->save();
        $this->auditLog->record('user.deactivated', $request->user('tenant'), "Deactivated user \"{$user->name}\"", $user);

        return back()->with('status', 'User deactivated.');
    }

    public function assignRoles(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageRoles', $user);

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $roles = TenantRole::query()->whereIn('id', $data['roles'] ?? [])->get();

        if ($user->hasRole('Tenant Owner') && ! $roles->contains('name', 'Tenant Owner')) {
            $remainingOwners = User::role('Tenant Owner')->where('id', '!=', $user->id)->count();
            abort_if($remainingOwners === 0, 422, 'At least one Tenant Owner must remain.');
        }

        $oldRoles = $user->roles->pluck('name')->all();
        $user->syncRoles($roles);

        $this->auditLog->record(
            event: 'user.roles_changed',
            actor: $request->user('tenant'),
            description: "Changed roles for \"{$user->name}\"",
            subject: $user,
            oldValues: ['roles' => $oldRoles],
            newValues: ['roles' => $roles->pluck('name')->all()],
        );

        return back()->with('status', 'Roles updated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:activate,suspend,deactivate'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $users = User::query()->whereIn('id', $data['user_ids'])->get();
        $count = 0;

        foreach ($users as $user) {
            $target = match ($data['action']) {
                'activate' => UserStatus::Active,
                'suspend' => UserStatus::Suspended,
                'deactivate' => UserStatus::Deactivated,
            };

            if (! Gate::allows($data['action'] === 'activate' ? 'update' : 'suspend', $user)) {
                continue;
            }

            if (! $user->status->canTransitionTo($target)) {
                continue;
            }

            $user->forceFill(array_filter([
                'status' => $target,
                'suspended_at' => $target === UserStatus::Suspended ? now() : ($target === UserStatus::Active ? null : $user->suspended_at),
                'deactivated_at' => $target === UserStatus::Deactivated ? now() : $user->deactivated_at,
            ]))->save();
            $count++;
        }

        $this->auditLog->record('user.bulk_action', $request->user('tenant'), "Bulk {$data['action']} applied to {$count} user(s)", metadata: ['action' => $data['action'], 'count' => $count]);

        return back()->with('status', "{$count} user(s) updated.");
    }
}
