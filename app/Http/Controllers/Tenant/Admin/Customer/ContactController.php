<?php

namespace App\Http\Controllers\Tenant\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\ContactRequest;
use App\Models\Customer\Company;
use App\Models\Customer\Contact;
use App\Models\Customer\CustomField;
use App\Models\Customer\Tag;
use App\Models\User;
use App\Services\Customer\CustomerTimelineService;
use App\Services\Customer\CustomFieldValueService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private readonly CustomerTimelineService $timeline,
        private readonly CustomFieldValueService $customFields,
        private readonly TenantAuditLogService $audit,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Contact::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,inactive,blocked,vip'],
            'company' => ['nullable', 'integer'], 'owner' => ['nullable', 'integer'], 'tag' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:display_name,email,status,last_contacted_at,created_at'], 'direction' => ['nullable', 'in:asc,desc'],
            'archived' => ['nullable', 'boolean'],
        ]);

        $contacts = Contact::query()
            ->when($request->boolean('archived'), fn ($query) => $query->onlyTrashed())
            ->with(['company:id,public_uuid,name', 'owner:id,name', 'tags:id,public_uuid,name,color'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('external_id', $search);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['company'] ?? null, fn ($query, $company) => $query->where('company_id', $company))
            ->when($filters['owner'] ?? null, fn ($query, $owner) => $query->where('owner_id', $owner))
            ->when($filters['tag'] ?? null, fn ($query, $tag) => $query->whereHas('tags', fn ($query) => $query->whereKey($tag)))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate(25)->withQueryString();

        return Inertia::render('Tenant/Admin/Customers/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'owners' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Contact::class);
        return Inertia::render('Tenant/Admin/Customers/Contacts/Form', $this->formData());
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = DB::transaction(function () use ($request): Contact {
            $data = $request->validated();
            $data['display_name'] = $data['display_name'] ?: trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($data['email'] ?? $data['phone']);
            $data['source'] ??= 'manual';
            $data['created_by'] = $request->user('tenant')->id;
            $contact = Contact::create(Arr::except($data, ['contact_points', 'tag_ids', 'custom_fields']));
            $this->syncRelations($contact, $data, $request->user('tenant')->id);
            $this->timeline->record('contact.created', "Contact {$contact->display_name} was created.", $contact, actor: $request->user('tenant'), related: $contact);
            $this->audit->record('contact.created', $request->user('tenant'), "Created contact \"{$contact->display_name}\"", $contact, newValues: Arr::except($data, ['custom_fields']));
            return $contact;
        });

        return redirect()->route('tenant.admin.customers.contacts.show', $contact)->with('status', 'Contact created.');
    }

    public function show(Contact $contact): Response
    {
        Gate::authorize('view', $contact);
        $contact->load(['company:id,public_uuid,name', 'owner:id,name,email', 'contactPoints', 'tags:id,public_uuid,name,color', 'customFieldValues.field', 'activities.actor:id,name']);
        return Inertia::render('Tenant/Admin/Customers/Contacts/Show', ['contact' => $contact]);
    }

    public function edit(Contact $contact): Response
    {
        Gate::authorize('update', $contact);
        return Inertia::render('Tenant/Admin/Customers/Contacts/Form', array_merge($this->formData(), [
            'contact' => $contact->load(['contactPoints', 'tags:id']),
            'customValues' => $contact->customFieldValues()->with('field:id,key')->get()->mapWithKeys(fn ($value) => [$value->field->key => $value->value]),
        ]));
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        DB::transaction(function () use ($request, $contact): void {
            $data = $request->validated();
            $data['display_name'] = $data['display_name'] ?: trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($data['email'] ?? $data['phone']);
            $old = $contact->toArray();
            $contact->update(Arr::except($data, ['contact_points', 'tag_ids', 'custom_fields']));
            $this->syncRelations($contact, $data, $request->user('tenant')->id);
            $this->timeline->record('contact.updated', "Contact {$contact->display_name} was updated.", $contact, actor: $request->user('tenant'), related: $contact);
            $this->audit->record('contact.updated', $request->user('tenant'), "Updated contact \"{$contact->display_name}\"", $contact, oldValues: $old, newValues: Arr::except($data, ['custom_fields']));
        });
        return redirect()->route('tenant.admin.customers.contacts.show', $contact)->with('status', 'Contact updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();
        $this->audit->record('contact.archived', description: "Archived contact \"{$contact->display_name}\"", subject: $contact);
        return redirect()->route('tenant.admin.customers.contacts.index')->with('status', 'Contact archived.');
    }

    public function restore(string $contact): RedirectResponse
    {
        $model = Contact::onlyTrashed()->where('public_uuid', $contact)->firstOrFail();
        Gate::authorize('restore', $model);
        $model->restore();
        $this->timeline->record('contact.restored', "Contact {$model->display_name} was restored.", $model, related: $model);
        return back()->with('status', 'Contact restored.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['uuid'], 'action' => ['required', 'in:archive,status,assign_owner,add_tag,remove_tag'], 'value' => ['nullable']]);
        $contacts = Contact::query()->whereIn('public_uuid', $data['ids'])->get();
        foreach ($contacts as $contact) {
            Gate::authorize($data['action'] === 'archive' ? 'delete' : 'update', $contact);
        }
        DB::transaction(function () use ($contacts, $data, $request): void {
            foreach ($contacts as $contact) {
                match ($data['action']) {
                    'archive' => $contact->delete(),
                    'status' => $contact->update(['status' => $request->validate(['value' => ['required', 'in:active,inactive,blocked,vip']])['value']]),
                    'assign_owner' => $contact->update(['owner_id' => $request->validate(['value' => ['nullable', 'exists:users,id']])['value']]),
                    'add_tag' => $contact->tags()->syncWithoutDetaching([$request->validate(['value' => ['required', 'exists:tags,id']])['value'] => ['assigned_by' => $request->user('tenant')->id]]),
                    'remove_tag' => $contact->tags()->detach($request->validate(['value' => ['required', 'exists:tags,id']])['value']),
                };
            }
        });
        return back()->with('status', count($contacts).' contacts updated.');
    }

    private function syncRelations(Contact $contact, array $data, int $actorId): void
    {
        if (array_key_exists('contact_points', $data)) {
            $contact->contactPoints()->delete();
            foreach ($data['contact_points'] ?? [] as $point) {
                $contact->contactPoints()->create(array_merge($point, ['normalized_value' => Str::lower(trim($point['value']))]));
            }
        }
        if (array_key_exists('tag_ids', $data)) {
            $contact->tags()->sync(collect($data['tag_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $actorId]])->all());
        }
        $this->customFields->validateAndStore($contact, 'contact', $data['custom_fields'] ?? []);
    }

    private function formData(): array
    {
        return [
            'contact' => null, 'customValues' => [],
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'owners' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'color']),
            'customFields' => CustomField::query()->where('resource_type', 'contact')->where('active', true)->orderBy('display_order')->get(),
        ];
    }
}
