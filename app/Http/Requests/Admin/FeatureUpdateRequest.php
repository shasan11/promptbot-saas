<?php

namespace App\Http\Requests\Admin;

class FeatureUpdateRequest extends FeatureStoreRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['code'] = ['required', 'alpha_dash:ascii', 'max:100', 'unique:features,code,'.$this->route('feature')?->id];

        return $rules;
    }
}
