<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('support.manage');
    }

    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:open,pending,resolved,closed'],
            'priority' => ['sometimes', 'required', 'in:low,normal,high,urgent'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:central_users,id'],
            'requester_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requester_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'sla_due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
