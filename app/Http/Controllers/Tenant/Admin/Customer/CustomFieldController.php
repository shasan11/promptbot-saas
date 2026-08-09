<?php

namespace App\Http\Controllers\Tenant\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('custom_fields.manage'), 403);
        return Inertia::render('Tenant/Admin/Customers/CustomFields/Index', ['fields' => CustomField::query()->orderBy('resource_type')->orderBy('display_order')->paginate(40), 'resourceTypes' => CustomField::RESOURCE_TYPES, 'fieldTypes' => CustomField::FIELD_TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('custom_fields.manage'), 403);
        $data = $this->validated($request); $data['key'] = $data['key'] ?: Str::snake($data['label']); $data['created_by'] = $request->user('tenant')->id;
        CustomField::create($data); return back()->with('status', 'Custom field created.');
    }

    public function update(Request $request, CustomField $customField): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('custom_fields.manage'), 403);
        $customField->update($this->validated($request, $customField)); return back()->with('status', 'Custom field updated.');
    }

    public function destroy(Request $request, CustomField $customField): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('custom_fields.manage'), 403);
        $customField->update(['active' => false]); return back()->with('status', 'Custom field archived.');
    }

    private function validated(Request $request, ?CustomField $field = null): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:120'], 'key' => ['nullable', 'alpha_dash:ascii', 'max:120', Rule::unique('custom_fields')->where('resource_type', $request->input('resource_type'))->ignore($field?->id)],
            'resource_type' => ['required', Rule::in(CustomField::RESOURCE_TYPES)], 'field_type' => ['required', Rule::in(CustomField::FIELD_TYPES)],
            'required' => ['boolean'], 'default_value' => ['nullable'], 'options' => ['nullable', 'array'], 'options.*' => ['string', 'max:255'],
            'validation' => ['nullable', 'array'], 'placeholder' => ['nullable', 'string', 'max:255'], 'help_text' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['integer', 'min:0', 'max:10000'], 'active' => ['boolean'],
        ]);
    }
}
