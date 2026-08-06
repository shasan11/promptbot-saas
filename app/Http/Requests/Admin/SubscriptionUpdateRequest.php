<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('subscriptions.update');
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['sometimes', 'required', 'exists:plans,id'],
            'status' => ['sometimes', 'required', 'in:trial,active,past_due,cancelled,expired,suspended,manual'],
            'billing_interval' => ['sometimes', 'required', 'in:monthly,annual'],
            'starts_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_starts_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'grace_ends_at' => ['nullable', 'date'],
            'external_provider' => ['nullable', 'string'],
            'external_id' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
