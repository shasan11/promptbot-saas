<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('central')?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'annual_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer'],
            'is_recommended' => ['required', 'boolean'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:0'],
            'resource_limits' => ['nullable', 'array'],
            'features' => ['nullable', 'array'],
            'features.*.id' => ['required_with:features', 'exists:features,id'],
            'features.*.enabled' => ['nullable', 'boolean'],
            'features.*.limit' => ['nullable', 'integer', 'min:0'],
            'features.*.unlimited' => ['nullable', 'boolean'],
        ];
    }
}
