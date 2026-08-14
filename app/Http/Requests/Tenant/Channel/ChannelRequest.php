<?php

namespace App\Http\Requests\Tenant\Channel;

use App\Models\Channel\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChannelRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user('tenant')?->can($this->isMethod('post') ? 'channels.create' : 'channels.update'); }
    public function rules(): array
    {
        $type = $this->input('type', $this->route('channel')?->type);
        return [
            'type' => ['required', Rule::in(Channel::TYPES)], 'name' => ['required', 'string', 'max:255'], 'status' => ['required', 'in:draft,active,disabled'],
            'team_id' => ['nullable', 'exists:teams,id'], 'default_assignee_id' => ['nullable', 'exists:users,id'], 'business_hours_policy_id' => ['nullable', 'exists:business_hour_policies,id'],
            'auto_reply_enabled' => ['boolean'], 'signature' => ['nullable', 'string', 'max:10000'],
            'email.inbox_address' => [Rule::requiredIf($type === 'email'), 'nullable', 'email:rfc', 'max:255'], 'email.incoming_provider' => ['nullable', 'in:webhook,imap'],
            'email.outgoing_provider' => ['nullable', 'in:laravel_mail,smtp'], 'email.from_name' => ['nullable', 'string', 'max:255'], 'email.reply_to_address' => ['nullable', 'email:rfc'],
            'credentials.host' => ['nullable', 'string', 'max:255'], 'credentials.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'credentials.username' => ['nullable', 'string', 'max:255'], 'credentials.password' => ['nullable', 'string', 'max:1000'], 'credentials.encryption' => ['nullable', 'in:tls,ssl,none'],
            'widget.widget_name' => [Rule::requiredIf($type === 'web_chat'), 'nullable', 'string', 'max:255'], 'widget.primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'widget.launcher_position' => ['nullable', 'in:left,right'], 'widget.welcome_message' => ['nullable', 'string', 'max:500'], 'widget.offline_message' => ['nullable', 'string', 'max:500'],
            'widget.supported_languages' => ['nullable', 'array'], 'widget.supported_languages.*' => ['string', 'max:12'], 'widget.allowed_origins' => ['nullable', 'array'], 'widget.allowed_origins.*' => ['url:http,https'],
            'widget.privacy_url' => ['nullable', 'url:http,https'], 'widget.terms_url' => ['nullable', 'url:http,https'], 'widget.allow_attachments' => ['boolean'], 'widget.require_email' => ['boolean'], 'widget.require_name' => ['boolean'],
            'widget.ai_auto_reply_enabled' => ['boolean'], 'widget.knowledge_base_id' => ['nullable', 'exists:knowledge_bases,id'],
        ];
    }
}
