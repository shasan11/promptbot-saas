<?php

namespace App\Http\Requests\Tenant\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->isMethod('post') ? 'create' : 'update';

        return (bool) $this->user('tenant')?->can("departments.{$ability}");
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'parent_id' => [
                'nullable',
                'exists:departments,id',
                Rule::notIn([$this->route('department')?->id]),
            ],
            'status' => ['required', 'in:active,archived'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && ! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }
}
