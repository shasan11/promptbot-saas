<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\Ticket\Ticket;
use App\Services\AI\TicketCopilotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketCopilotController extends Controller
{
    public function run(Request $request, Ticket $ticket, TicketCopilotService $copilot): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.copilot.use'), 403);
        $data = $request->validate(['operation' => ['required','in:summary,draft,action_items']]);
        $copilot->perform($ticket, $data['operation'], $request->user('tenant'));
        return back()->with('status', 'Ticket AI suggestion generated for review.');
    }
}
