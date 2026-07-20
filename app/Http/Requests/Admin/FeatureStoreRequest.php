<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FeatureStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('central')?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash:ascii', 'max:100', 'unique:features,code'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:boolean,limited'],
        ];
    }
}
