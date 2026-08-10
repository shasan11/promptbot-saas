<?php

namespace App\Http\Requests\Tenant\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAgentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('tenant')?->can('ai.agents.manage') ?? false; }
    public function rules(): array
    {
        return [
            'name' => ['required','string','max:120'], 'description' => ['nullable','string','max:2000'],
            'purpose' => ['nullable','string','max:255'], 'system_instructions' => ['required','string','min:20','max:30000'],
            'provider_config_id' => ['required','integer','exists:ai_provider_configs,id'], 'model' => ['nullable','string','max:150'],
            'temperature' => ['required','numeric','min:0','max:1.5'], 'max_tokens' => ['required','integer','min:64','max:8192'],
            'reasoning_effort' => ['required', Rule::in(['off','low','medium','high'])],
            'deployment_mode' => ['required', Rule::in(['copilot','draft_only','autonomous'])],
            'require_citations' => ['required','boolean'], 'human_approval_mode' => ['required', Rule::in(['always','risk_based','never'])],
            'max_context_tokens' => ['required','integer','min:1000','max:32000'], 'max_tool_calls' => ['required','integer','min:0','max:10'],
            'max_steps' => ['required','integer','min:1','max:15'], 'timeout_seconds' => ['required','integer','min:5','max:120'],
            'memory_enabled' => ['required','boolean'], 'memory_strategy' => ['required', Rule::in(['none','recent_with_summary','conversation_transcript'])],
            'auto_reply_enabled' => ['required','boolean'],
        ];
    }
}
