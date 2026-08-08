<?php

namespace App\Http\Requests\Tenant\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BusinessHourPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('workspace.manage_business_hours');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'is_default' => ['boolean'],
            'status' => ['required', 'in:active,inactive'],
            'intervals' => ['array'],
            'intervals.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'intervals.*.starts_at' => ['required', 'date_format:H:i'],
            'intervals.*.ends_at' => ['required', 'date_format:H:i', 'after:intervals.*.starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $intervals = collect($this->input('intervals', []));

            if ($this->input('status') === 'active' && $intervals->isEmpty()) {
                $validator->errors()->add('intervals', 'An active policy needs at least one working interval.');
            }

            $intervals->groupBy('day_of_week')->each(function ($dayIntervals, $day) use ($validator) {
                $sorted = $dayIntervals->sortBy('starts_at')->values();

                for ($i = 0; $i < $sorted->count() - 1; $i++) {
                    if ($sorted[$i]['ends_at'] > $sorted[$i + 1]['starts_at']) {
                        $validator->errors()->add('intervals', "Overlapping intervals found for day {$day}.");
                    }
                }
            });
        });
    }
}
