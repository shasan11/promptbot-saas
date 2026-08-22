<?php

namespace App\Http\Requests\Tenant\Channel;

use Illuminate\Foundation\Http\FormRequest;

class BotProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can($this->isMethod('post') ? 'channels.create' : 'channels.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tone' => ['required', 'in:professional,friendly,casual'],
            'response_length' => ['required', 'in:short,balanced,detailed'],
            'language_policy' => ['required', 'in:match_customer,always_default'],
            'default_language' => ['required', 'string', 'max:12'],
            'escalate_on_request' => ['boolean'],
            // 0 means "never escalate on failures". The upper bound keeps a
            // typo from turning handoff off in practice by setting it to 200.
            'escalate_after_failures' => ['required', 'integer', 'min:0', 'max:10'],
            'escalate_on_negative_sentiment' => ['boolean'],
            'escalate_on_risk_flags' => ['boolean'],
            'escalation_team_id' => ['nullable', 'exists:teams,id'],
            'is_default' => ['boolean'],
        ];
    }
}
