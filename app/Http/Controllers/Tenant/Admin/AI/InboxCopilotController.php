<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\Suggestion;
use App\Models\Inbox\Conversation;
use App\Services\AI\InboxCopilotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InboxCopilotController extends Controller
{
    public function run(Request $request, Conversation $conversation, InboxCopilotService $copilot): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.copilot.use'), 403);
        $data = $request->validate(['operation' => ['required','in:summary,classify,draft,rewrite,translate,suggest'], 'text' => ['nullable','string','max:50000'], 'language' => ['nullable','string','max:50']]);
        $copilot->perform($conversation, $data['operation'], $request->user('tenant'), $data);
        return back()->with('status', 'AI copilot suggestion generated for review.');
    }

    public function feedback(Request $request, Suggestion $suggestion): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.feedback.create'), 403);
        $data = $request->validate(['decision' => ['required','in:accepted,rejected'], 'comment' => ['nullable','string','max:2000']]);
        $accepted = $data['decision'] === 'accepted';
        $suggestion->forceFill([$accepted ? 'accepted_by' : 'rejected_by' => $request->user('tenant')->id, $accepted ? 'accepted_at' : 'rejected_at' => now(), 'status' => $data['decision']])->save();
        $suggestion->feedback()->create(['ai_run_id' => $suggestion->ai_run_id, 'agent_id' => $suggestion->agent_id, 'user_id' => $request->user('tenant')->id, 'rating' => $accepted ? 'helpful' : 'not_helpful', 'comment' => $data['comment'] ?? null]);
        return back()->with('status', $accepted ? 'Suggestion accepted for use.' : 'Suggestion rejected.');
    }
}
