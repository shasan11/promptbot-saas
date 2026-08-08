<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Administration\TeamRequest;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $auditLog) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Team::class);

        return Inertia::render('Tenant/Admin/Administration/Teams/Index', [
            'teams' => Team::query()->withCount('members')->with(['lead:id,name', 'department:id,name'])->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Team::class);

        return Inertia::render('Tenant/Admin/Administration/Teams/Create', [
            'team' => null,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['created_by'] = $request->user('tenant')?->id;

        $team = Team::create($data);

        $this->auditLog->record('team.created', $request->user('tenant'), "Created team \"{$team->name}\"", $team, newValues: $data);

        return redirect()->route('tenant.admin.administration.teams.show', $team)->with('status', 'Team created.');
    }

    public function show(Team $team): Response
    {
        Gate::authorize('view', $team);

        return Inertia::render('Tenant/Admin/Administration/Teams/Show', [
            'team' => $team->load(['lead:id,name', 'department:id,name', 'members:id,name,email,status']),
            'availableUsers' => User::query()->whereDoesntHave('teams', fn ($q) => $q->where('teams.id', $team->id))->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function edit(Team $team): Response
    {
        Gate::authorize('update', $team);

        return Inertia::render('Tenant/Admin/Administration/Teams/Create', [
            'team' => $team,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $oldValues = $team->only(['name', 'lead_user_id', 'department_id', 'status']);
        $team->update($request->validated());

        $this->auditLog->record('team.updated', $request->user('tenant'), "Updated team \"{$team->name}\"", $team, oldValues: $oldValues, newValues: $request->validated());

        return redirect()->route('tenant.admin.administration.teams.show', $team)->with('status', 'Team updated.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $name = $team->name;
        $team->members()->detach();
        $team->delete();

        $this->auditLog->record('team.deleted', request()->user('tenant'), "Deleted team \"{$name}\"", subjectType: Team::class, subjectLabel: $name);

        return redirect()->route('tenant.admin.administration.teams.index')->with('status', 'Team deleted.');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $team->members()->syncWithoutDetaching([$data['user_id']]);

        $this->auditLog->record('team.member_added', $request->user('tenant'), "Added member to \"{$team->name}\"", $team, newValues: ['user_id' => $data['user_id']]);

        return back()->with('status', 'Member added.');
    }

    public function removeMember(Request $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        $team->members()->detach($user->id);

        if ($team->lead_user_id === $user->id) {
            $team->update(['lead_user_id' => null]);
        }

        $this->auditLog->record('team.member_removed', $request->user('tenant'), "Removed member from \"{$team->name}\"", $team, newValues: ['user_id' => $user->id]);

        return back()->with('status', 'Member removed.');
    }

    public function setLead(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $data = $request->validate(['user_id' => ['nullable', 'exists:users,id']]);

        if (! empty($data['user_id'])) {
            $team->members()->syncWithoutDetaching([$data['user_id']]);
        }

        $team->update(['lead_user_id' => $data['user_id'] ?? null]);

        return back()->with('status', 'Team lead updated.');
    }

    public function archive(Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);
        $team->update(['status' => 'archived', 'archived_at' => now()]);

        return back()->with('status', 'Team archived.');
    }

    public function restore(Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);
        $team->update(['status' => 'active', 'archived_at' => null]);

        return back()->with('status', 'Team restored.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Team::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
