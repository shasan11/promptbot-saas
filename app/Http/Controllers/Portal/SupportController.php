<?php

namespace App\Http\Controllers\Portal;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\CustomerAccountActivity;
use App\Models\PortalUser;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\SupportAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportController extends PortalController
{
    public function index(Request $request, PlatformSettingsService $settings): Response
    {
        abort_unless($this->supportEnabled($settings), 403);
        $account = $this->account($request);
        $this->authorize('manageSupport', $account);
        return Inertia::render('Portal/Support/Index', [
            'tickets' => $account->supportTickets()->where(fn ($query) => $query->whereNull('tenant_id')->orWhereIn('tenant_id', $this->visibleTenantIds($request)))
                ->with('tenant:id,company_name,public_uuid')->latest('last_activity_at')->paginate(20),
        ]);
    }

    public function create(Request $request, PlatformSettingsService $settings): Response
    {
        abort_unless($this->supportEnabled($settings), 403);
        $account = $this->account($request);
        $this->authorize('manageSupport', $account);
        $workspaces = $account->tenantsVisibleTo($request->user('portal'))->orderBy('company_name')->get(['id', 'public_uuid', 'company_name']);
        $selected = $request->string('workspace')->toString();
        return Inertia::render('Portal/Support/Create', [
            'workspaces' => $workspaces,
            'selectedWorkspace' => $workspaces->contains(fn ($workspace) => $workspace->getKey() === $selected) ? $selected : '',
        ]);
    }

    public function store(Request $request, PlatformSettingsService $settings, SupportAttachmentService $attachments): RedirectResponse
    {
        abort_unless($this->supportEnabled($settings), 403);
        $account = $this->account($request);
        $this->authorize('manageSupport', $account);
        $data = $request->validate([
            'tenant_id' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'in:general,billing,technical,workspace'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'attachment' => SupportAttachmentService::RULES,
        ]);
        if (! empty($data['tenant_id'])) abort_unless($account->tenantsVisibleTo($request->user('portal'))->whereKey($data['tenant_id'])->exists(), 422, 'Invalid workspace.');

        $ticket = DB::transaction(function () use ($account, $request, $data, $attachments): SupportTicket {
            $sequence = SupportTicket::query()->lockForUpdate()->count() + 1;
            $ticket = SupportTicket::create([
                ...$data, 'number' => 'TKT-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'customer_account_id' => $account->getKey(), 'portal_user_id' => $request->user('portal')->getKey(),
                'requester_name' => $request->user('portal')->name, 'requester_email' => $request->user('portal')->email,
                'status' => 'open', 'last_activity_at' => now(),
            ]);
            if ($request->hasFile('attachment')) {
                $attachments->createMessage($ticket, [
                    'portal_user_id' => $request->user('portal')->getKey(),
                    'body' => 'Attachment submitted with the original request.',
                    'is_internal' => false,
                ], $request->file('attachment'));
            }
            CustomerAccountActivity::create([
                'customer_account_id' => $account->getKey(), 'tenant_id' => $ticket->tenant_id,
                'actor_type' => PortalUser::class, 'actor_id' => (string) $request->user('portal')->getKey(),
                'event' => 'support.ticket_created', 'subject_type' => SupportTicket::class,
                'subject_id' => (string) $ticket->getKey(), 'description' => "Support ticket {$ticket->number} was created.",
            ]);
            return $ticket;
        });
        return redirect()->route('portal.support.show', $ticket)->with('status', 'Support ticket created.');
    }

    public function show(Request $request, SupportTicket $ticket, PlatformSettingsService $settings): Response
    {
        abort_unless($this->supportEnabled($settings), 403);
        $this->authorize('view', $ticket);
        return Inertia::render('Portal/Support/Show', [
            'ticket' => $ticket->load(['tenant:id,company_name,public_uuid', 'messages' => fn ($query) => $query->where('is_internal', false)->with(['centralUser:id,name', 'portalUser:id,name'])]),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, PlatformSettingsService $settings, SupportAttachmentService $attachments): RedirectResponse
    {
        abort_unless($this->supportEnabled($settings), 403);
        $this->authorize('update', $ticket);
        abort_if($ticket->status === 'closed', 422, 'This ticket is closed.');
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'attachment' => SupportAttachmentService::RULES]);
        $attachments->createMessage($ticket, ['portal_user_id' => $request->user('portal')->getKey(), 'body' => $data['body'], 'is_internal' => false], $request->file('attachment'));
        $ticket->update(['last_activity_at' => now(), 'status' => $ticket->status === 'resolved' ? 'open' : $ticket->status]);
        CustomerAccountActivity::create([
            'customer_account_id' => $ticket->customer_account_id, 'tenant_id' => $ticket->tenant_id,
            'actor_type' => PortalUser::class, 'actor_id' => (string) $request->user('portal')->getKey(),
            'event' => 'support.customer_replied', 'subject_type' => SupportTicket::class,
            'subject_id' => (string) $ticket->getKey(), 'description' => "A customer replied to {$ticket->number}.",
        ]);
        return back()->with('status', 'Reply sent.');
    }

    public function downloadAttachment(Request $request, SupportTicket $ticket, SupportTicketMessage $message, PlatformSettingsService $settings): StreamedResponse
    {
        abort_unless($this->supportEnabled($settings), 403);
        $this->authorize('view', $ticket);
        abort_unless($message->support_ticket_id === $ticket->getKey() && ! $message->is_internal && $message->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);
        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: 'support-attachment', [
            'Content-Type' => $message->attachment_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function close(Request $request, SupportTicket $ticket, PlatformSettingsService $settings): RedirectResponse
    {
        abort_unless($this->supportEnabled($settings), 403);
        $this->authorize('update', $ticket);
        $ticket->update(['status' => 'closed', 'closed_at' => now(), 'last_activity_at' => now()]);
        CustomerAccountActivity::create([
            'customer_account_id' => $ticket->customer_account_id, 'tenant_id' => $ticket->tenant_id,
            'actor_type' => PortalUser::class, 'actor_id' => (string) $request->user('portal')->getKey(),
            'event' => 'support.ticket_closed', 'subject_type' => SupportTicket::class,
            'subject_id' => (string) $ticket->getKey(), 'description' => "Support ticket {$ticket->number} was closed.",
        ]);
        return back()->with('status', 'Ticket closed.');
    }

    private function supportEnabled(PlatformSettingsService $settings): bool
    {
        return filter_var($settings->get('customer_portal', 'support_tickets_enabled', $settings->get('customer_portal', 'allow_support_tickets', true)), FILTER_VALIDATE_BOOL);
    }
}
