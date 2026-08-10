<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\Agent;
use App\Models\AI\Suggestion;
use App\Models\Customer\Company;
use App\Models\Customer\Contact;
use App\Models\Inbox\Conversation;
use App\Models\Ticket\Ticket;
use App\Services\AI\TenantAgentRuntime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerBriefController extends Controller
{
    public function contact(Request $request, Contact $contact, TenantAgentRuntime $runtime): RedirectResponse { return $this->generate($request, $contact, $runtime); }
    public function company(Request $request, Company $company, TenantAgentRuntime $runtime): RedirectResponse { return $this->generate($request, $company, $runtime); }

    private function generate(Request $request, Model $resource, TenantAgentRuntime $runtime): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.copilot.use'), 403);
        $agent = Agent::query()->where('status', 'active')->firstOrFail();
        if ($resource instanceof Contact) {
            $facts = ['name' => $resource->display_name, 'status' => $resource->status, 'company' => $resource->company?->name, 'conversations' => Conversation::query()->where('contact_id', $resource->id)->count(), 'open_conversations' => Conversation::query()->where('contact_id', $resource->id)->whereNotIn('status', ['resolved','closed'])->count(), 'tickets' => Ticket::query()->where('contact_id', $resource->id)->count(), 'recent_activity' => $resource->activities()->latest('occurred_at')->limit(15)->pluck('description')];
        } else {
            $facts = ['name' => $resource->name, 'status' => $resource->status, 'industry' => $resource->industry, 'contacts' => $resource->contacts()->count(), 'conversations' => Conversation::query()->where('company_id', $resource->id)->count(), 'open_conversations' => Conversation::query()->where('company_id', $resource->id)->whereNotIn('status', ['resolved','closed'])->count(), 'tickets' => Ticket::query()->where('company_id', $resource->id)->count(), 'recent_activity' => $resource->activities()->latest('occurred_at')->limit(15)->pluck('description')];
        }
        $result = $runtime->chat($agent, 'Create a concise support brief from these first-party aggregate facts. Separate verified facts, open workload, and suggested questions. Do not infer customer sentiment, causes, revenue, risk, or intent unless explicitly present. Facts: '.json_encode($facts), $request->user('tenant'), 'customer_brief', 'brief', false);
        Suggestion::query()->where('public_uuid', $result['suggestion_uuid'])->update(['resource_type' => $resource::class, 'resource_id' => $resource->id, 'type' => 'customer_brief']);
        return back()->with('status', 'AI support brief generated from verified workspace facts.');
    }
}
