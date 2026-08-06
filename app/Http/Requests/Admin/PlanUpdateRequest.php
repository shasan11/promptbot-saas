<?php

namespace App\Http\Requests\Admin;

class PlanUpdateRequest extends PlanStoreRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('plans.update');
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['slug'] = ['required', 'alpha_dash:ascii', 'max:100', 'unique:plans,slug,'.$this->route('plan')?->id];

        return $rules;
    }
}
