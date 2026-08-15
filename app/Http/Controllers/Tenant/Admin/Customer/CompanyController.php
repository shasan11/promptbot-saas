<?php

namespace App\Http\Controllers\Tenant\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\CompanyRequest;
use App\Models\Customer\Company;
use App\Models\Customer\CustomField;
use App\Models\Customer\Tag;
use App\Models\AI\Suggestion;
use App\Models\User;
use App\Services\Customer\CustomerTimelineService;
use App\Services\Customer\CustomFieldValueService;
use App\Services\SaaS\TenantFeatureService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(private readonly CustomerTimelineService $timeline, private readonly CustomFieldValueService $customFields, private readonly TenantAuditLogService $audit) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Company::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,inactive,archived'], 'industry' => ['nullable', 'string', 'max:120'], 'archived' => ['nullable', 'boolean']]);
        $companies = Company::query()->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->with('accountOwner:id,name')->withCount('contacts')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('domain', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['industry'] ?? null, fn ($q, $industry) => $q->where('industry', $industry))
            ->orderBy('name')->paginate(25)->withQueryString();
        return Inertia::render('Tenant/Admin/Customers/Companies/Index', ['companies' => $companies, 'filters' => $filters, 'industries' => Company::query()->whereNotNull('industry')->distinct()->orderBy('industry')->pluck('industry')]);
    }

    public function create(): Response { Gate::authorize('create', Company::class); return Inertia::render('Tenant/Admin/Customers/Companies/Form', $this->formData()); }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = DB::transaction(function () use ($request): Company {
            $data = $request->validated(); $data['created_by'] = $request->user('tenant')->id;
            $company = Company::create(Arr::except($data, ['tag_ids', 'custom_fields']));
            $this->syncRelations($company, $data, $request->user('tenant')->id);
            $this->timeline->record('company.created', "Company {$company->name} was created.", company: $company, related: $company);
            $this->audit->record('company.created', description: "Created company \"{$company->name}\"", subject: $company, newValues: Arr::except($data, ['custom_fields']));
            return $company;
        });
        return redirect()->route('tenant.admin.customers.companies.show', $company)->with('status', 'Company created.');
    }

    public function show(Company $company, TenantFeatureService $features): Response
    {
        Gate::authorize('view', $company);
        $company->load(['accountOwner:id,name,email', 'tags:id,public_uuid,name,color', 'customFieldValues.field', 'activities.actor:id,name']);
        $contacts = $company->contacts()->with('owner:id,name')->orderBy('display_name')->paginate(15);
        $aiEnabled = request()->user('tenant')->can('ai.copilot.use') && $features->enabled('ai_platform');
        $brief = $aiEnabled ? Suggestion::query()->where('resource_type', Company::class)->where('resource_id', $company->id)->latest()->first() : null;
        return Inertia::render('Tenant/Admin/Customers/Companies/Show', [
            'company' => $company, 'contacts' => $contacts,
            'ai' => $aiEnabled ? ['brief' => $brief ? ['text' => $brief->text, 'created_at' => $brief->created_at] : null] : null,
        ]);
    }

    public function edit(Company $company): Response
    {
        Gate::authorize('update', $company);
        return Inertia::render('Tenant/Admin/Customers/Companies/Form', array_merge($this->formData(), ['company' => $company->load('tags:id'), 'customValues' => $company->customFieldValues()->with('field:id,key')->get()->mapWithKeys(fn ($v) => [$v->field->key => $v->value])]));
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        DB::transaction(function () use ($request, $company): void {
            $old = $company->toArray(); $data = $request->validated();
            $company->update(Arr::except($data, ['tag_ids', 'custom_fields']));
            $this->syncRelations($company, $data, $request->user('tenant')->id);
            $this->timeline->record('company.updated', "Company {$company->name} was updated.", company: $company, related: $company);
            $this->audit->record('company.updated', description: "Updated company \"{$company->name}\"", subject: $company, oldValues: $old, newValues: Arr::except($data, ['custom_fields']));
        });
        return redirect()->route('tenant.admin.customers.companies.show', $company)->with('status', 'Company updated.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        Gate::authorize('delete', $company);
        $company->delete();
        $this->audit->record('company.archived', description: "Archived company \"{$company->name}\"", subject: $company);
        return redirect()->route('tenant.admin.customers.companies.index')->with('status', 'Company archived.');
    }

    public function restore(string $company): RedirectResponse
    {
        $model = Company::onlyTrashed()->where('public_uuid', $company)->firstOrFail(); Gate::authorize('restore', $model); $model->restore();
        $this->timeline->record('company.restored', "Company {$model->name} was restored.", company: $model, related: $model);
        return back()->with('status', 'Company restored.');
    }

    private function syncRelations(Company $company, array $data, int $actorId): void
    {
        if (array_key_exists('tag_ids', $data)) $company->tags()->sync(collect($data['tag_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $actorId]])->all());
        $this->customFields->validateAndStore($company, 'company', $data['custom_fields'] ?? []);
    }

    private function formData(): array
    {
        return ['company' => null, 'customValues' => [], 'owners' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'tags' => Tag::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'color']), 'customFields' => CustomField::query()->where('resource_type', 'company')->where('active', true)->orderBy('display_order')->get()];
    }
}
