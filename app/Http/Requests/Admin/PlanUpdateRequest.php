<?php

namespace App\Http\Requests\Admin;

class PlanUpdateRequest extends PlanStoreRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['slug'] = ['required', 'alpha_dash:ascii', 'max:100', 'unique:plans,slug,'.$this->route('plan')?->id];

        return $rules;
    }
}
