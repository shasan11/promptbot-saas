<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Administration\BusinessHourPolicyRequest;
use App\Models\BusinessHourPolicy;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessHourController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $auditLog) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', BusinessHourPolicy::class);

        return Inertia::render('Tenant/Admin/Administration/BusinessHours/Index', [
            'policies' => BusinessHourPolicy::query()->with('intervals')->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', BusinessHourPolicy::class);

        return Inertia::render('Tenant/Admin/Administration/BusinessHours/Create', ['policy' => null]);
    }

    public function store(BusinessHourPolicyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $policy = DB::transaction(function () use ($data, $request) {
            if (! empty($data['is_default'])) {
                BusinessHourPolicy::query()->update(['is_default' => false]);
            }

            $policy = BusinessHourPolicy::create([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'is_default' => (bool) ($data['is_default'] ?? false),
                'status' => $data['status'],
                'created_by' => $request->user('tenant')?->id,
            ]);

            foreach ($data['intervals'] ?? [] as $interval) {
                $policy->intervals()->create($interval);
            }

            return $policy;
        });

        $this->auditLog->record('business_hours.created', $request->user('tenant'), "Created business hours policy \"{$policy->name}\"", $policy);

        return redirect()->route('tenant.admin.administration.business-hours.index')->with('status', 'Business hours policy created.');
    }

    public function edit(BusinessHourPolicy $businessHour): Response
    {
        Gate::authorize('update', $businessHour);

        return Inertia::render('Tenant/Admin/Administration/BusinessHours/Create', [
            'policy' => $businessHour->load('intervals'),
        ]);
    }

    public function update(BusinessHourPolicyRequest $request, BusinessHourPolicy $businessHour): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $businessHour) {
            if (! empty($data['is_default'])) {
                BusinessHourPolicy::query()->where('id', '!=', $businessHour->id)->update(['is_default' => false]);
            }

            $businessHour->update([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'is_default' => (bool) ($data['is_default'] ?? false),
                'status' => $data['status'],
            ]);

            $businessHour->intervals()->delete();
            foreach ($data['intervals'] ?? [] as $interval) {
                $businessHour->intervals()->create($interval);
            }
        });

        $this->auditLog->record('business_hours.updated', $request->user('tenant'), "Updated business hours policy \"{$businessHour->name}\"", $businessHour);

        return redirect()->route('tenant.admin.administration.business-hours.index')->with('status', 'Business hours policy updated.');
    }

    public function destroy(BusinessHourPolicy $businessHour): RedirectResponse
    {
        Gate::authorize('delete', $businessHour);

        $name = $businessHour->name;
        $businessHour->delete();

        $this->auditLog->record('business_hours.deleted', request()->user('tenant'), "Deleted business hours policy \"{$name}\"", subjectType: BusinessHourPolicy::class, subjectLabel: $name);

        return back()->with('status', 'Business hours policy deleted.');
    }
}
