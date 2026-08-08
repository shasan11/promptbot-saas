<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Administration\DepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $auditLog) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Department::class);

        return Inertia::render('Tenant/Admin/Administration/Departments/Index', [
            'departments' => Department::query()
                ->withCount('users')
                ->with('head:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Department::class);

        return Inertia::render('Tenant/Admin/Administration/Departments/Create', [
            'department' => null,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['created_by'] = $request->user('tenant')?->id;

        $department = Department::create($data);

        $this->auditLog->record('department.created', $request->user('tenant'), "Created department \"{$department->name}\"", $department, newValues: $data);

        return redirect()->route('tenant.admin.administration.departments.index')->with('status', 'Department created.');
    }

    public function edit(Department $department): Response
    {
        Gate::authorize('update', $department);

        return Inertia::render('Tenant/Admin/Administration/Departments/Create', [
            'department' => $department,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('status', 'active')->where('id', '!=', $department->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $oldValues = $department->only(['name', 'head_user_id', 'parent_id', 'status']);
        $department->update($request->validated());

        $this->auditLog->record('department.updated', $request->user('tenant'), "Updated department \"{$department->name}\"", $department, oldValues: $oldValues, newValues: $request->validated());

        return redirect()->route('tenant.admin.administration.departments.index')->with('status', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        Gate::authorize('delete', $department);

        if ($department->users()->exists()) {
            return back()->with('error', 'Reassign users before deleting this department.');
        }

        $name = $department->name;
        $department->delete();

        $this->auditLog->record('department.deleted', request()->user('tenant'), "Deleted department \"{$name}\"", subjectType: Department::class, subjectLabel: $name);

        return back()->with('status', 'Department deleted.');
    }

    public function archive(Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);
        $department->update(['status' => 'archived', 'archived_at' => now()]);

        return back()->with('status', 'Department archived.');
    }

    public function restore(Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);
        $department->update(['status' => 'active', 'archived_at' => null]);

        return back()->with('status', 'Department restored.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Department::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
