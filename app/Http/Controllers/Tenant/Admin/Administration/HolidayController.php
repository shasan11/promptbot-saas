<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Administration\HolidayRequest;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\Team;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $auditLog) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Holiday::class);

        $year = $request->integer('year') ?: now()->year;

        return Inertia::render('Tenant/Admin/Administration/Holidays/Index', [
            'holidays' => Holiday::query()
                ->with(['department:id,name', 'team:id,name'])
                ->whereYear('date', $year)
                ->orderBy('date')
                ->get(),
            'year' => $year,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Holiday::class);

        return Inertia::render('Tenant/Admin/Administration/Holidays/Create', [
            'holiday' => null,
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user('tenant')?->id;

        $holiday = Holiday::create($data);

        $this->auditLog->record('holiday.created', $request->user('tenant'), "Added holiday \"{$holiday->name}\"", $holiday, newValues: $data);

        return redirect()->route('tenant.admin.administration.holidays.index', ['year' => $holiday->date->year])->with('status', 'Holiday added.');
    }

    public function edit(Holiday $holiday): Response
    {
        Gate::authorize('update', $holiday);

        return Inertia::render('Tenant/Admin/Administration/Holidays/Create', [
            'holiday' => $holiday,
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $oldValues = $holiday->only(['name', 'date', 'is_active']);
        $holiday->update($request->validated());

        $this->auditLog->record('holiday.updated', $request->user('tenant'), "Updated holiday \"{$holiday->name}\"", $holiday, oldValues: $oldValues, newValues: $request->validated());

        return redirect()->route('tenant.admin.administration.holidays.index', ['year' => $holiday->date->year])->with('status', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        Gate::authorize('delete', $holiday);

        $name = $holiday->name;
        $year = $holiday->date->year;
        $holiday->delete();

        $this->auditLog->record('holiday.deleted', request()->user('tenant'), "Removed holiday \"{$name}\"", subjectType: Holiday::class, subjectLabel: $name);

        return redirect()->route('tenant.admin.administration.holidays.index', ['year' => $year])->with('status', 'Holiday removed.');
    }
}
