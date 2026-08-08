<?php

namespace App\Http\Requests\Tenant\Administration;

use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('workspace.manage_business_hours');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'is_full_day' => ['boolean'],
            'starts_at' => ['nullable', 'required_if:is_full_day,false', 'date_format:H:i'],
            'ends_at' => ['nullable', 'required_if:is_full_day,false', 'date_format:H:i', 'after:starts_at'],
            'recurrence' => ['required', 'in:none,yearly'],
            'country' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'is_active' => ['boolean'],
        ];
    }
}
