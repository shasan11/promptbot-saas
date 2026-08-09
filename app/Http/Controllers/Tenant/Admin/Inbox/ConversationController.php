<?php

namespace App\Http\Controllers\Tenant\Admin\Inbox;

use App\Http\Controllers\Controller;
use App\Models\Inbox\Conversation;
use App\Models\Team;
use App\Models\User;
use App\Services\Inbox\ConversationService;
use App\Services\Sla\SlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $service, private readonly SlaService $sla) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Conversation::class);
        $filters = $request->validate(['view' => ['nullable', 'in:all,mine,unassigned,mentions,snoozed,open,pending,closed'], 'team' => ['nullable', 'integer'], 'channel' => ['nullable', 'integer'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'search' => ['nullable', 'string', 'max:255']]);
        $view = $filters['view'] ?? 'all'; $user = $request->user('tenant');
        $query = Conversation::query()->with(['contact:id,public_uuid,display_name,email,avatar_path', 'channel:id,public_uuid,name,type', 'assignee:id,name', 'team:id,name', 'latestMessage', 'tags:id,public_uuid,name,color']);
        match ($view) {
            'mine' => $query->where('assignee_id', $user->id), 'unassigned' => $query->whereNull('assignee_id'),
            'mentions' => $query->whereHas('messages.mentions', fn ($q) => $q->where('user_id', $user->id)->whereNull('read_at')),
            'snoozed' => $query->where('status', 'snoozed'), 'open', 'pending', 'closed' => $query->where('status', $view), default => null,
        };
        $query->when($filters['team'] ?? null, fn ($q, $id) => $q->where('team_id', $id))->when($filters['channel'] ?? null, fn ($q, $id) => $q->where('channel_id', $id))->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('subject', 'like', "%{$search}%")->orWhereHas('contact', fn ($q) => $q->where('display_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))));
        return Inertia::render('Tenant/Admin/Inbox/Index', ['conversations' => $query->orderByDesc('last_message_at')->paginate(30)->withQueryString(), 'filters' => $filters, 'teams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id','name']), 'channels' => \App\Models\Channel\Channel::query()->where('status','active')->orderBy('name')->get(['id','name','type'])]);
    }

    public function show(Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation); $conversation->update(['unread_count' => 0]);
        $conversation->load(['contact.company:id,public_uuid,name', 'contact.contactPoints', 'channel.emailSettings', 'team:id,name', 'assignee:id,name', 'followers:id,name', 'tags:id,public_uuid,name,color']);
        $messages = $conversation->messages()->with(['attachments', 'mentions.user:id,name'])->latest()->paginate(50)->through(function ($message) {
            $message->attachments->each(fn ($attachment) => $attachment->setAttribute('download_url', URL::temporarySignedRoute('tenant.admin.inbox.attachments.download', now()->addMinutes(10), $attachment)));
            return $message;
        });
        return Inertia::render('Tenant/Admin/Inbox/Show', ['conversation' => $conversation, 'messages' => $messages, 'users' => User::query()->where('status','active')->orderBy('name')->get(['id','name']), 'teams' => Team::query()->where('status','active')->orderBy('name')->get(['id','name'])]);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('reply', $conversation); $data = $request->validate(['body' => ['required','string','max:50000'], 'message_type' => ['required','in:text,note']]);
        if ($data['message_type'] !== 'note' && $conversation->channel->type === 'email' && ! $conversation->contact->email) return back()->withErrors(['body' => 'This contact has no email address.']);
        $this->service->reply($conversation->load(['channel.emailSettings','channel.credential','contact']), $request->user('tenant'), $data);
        return back()->with('status', $data['message_type'] === 'note' ? 'Internal note added.' : 'Reply sent.');
    }

    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('assign', $conversation); $data = $request->validate(['assignee_id' => ['nullable','exists:users,id'], 'team_id' => ['nullable','exists:teams,id'], 'reason' => ['nullable','string','max:255']]);
        $this->service->assign($conversation->load('contact'), $data['assignee_id'] ?? null, $data['team_id'] ?? null, $request->user('tenant'), $data['reason'] ?? null); return back()->with('status','Conversation assigned.');
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('update', $conversation); $data = $request->validate(['status' => ['sometimes','in:open,pending,waiting_on_customer,snoozed,resolved,closed'], 'priority' => ['sometimes','in:low,normal,high,urgent'], 'snoozed_until' => ['nullable','date','after:now']]);
        if (($data['status'] ?? null) === 'snoozed' && empty($data['snoozed_until'])) return back()->withErrors(['snoozed_until' => 'Choose when this conversation should reopen.']);
        if (isset($data['status'])) { $data['resolved_at'] = $data['status'] === 'resolved' ? now() : null; $data['closed_at'] = $data['status'] === 'closed' ? now() : null; if ($data['status'] !== 'snoozed') $data['snoozed_until'] = null; }
        $conversation->update($data); if (in_array($data['status'] ?? null, ['resolved','closed'], true)) $this->sla->fulfillResolution($conversation); return back()->with('status','Conversation updated.');
    }

    public function follow(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation); $conversation->followers()->toggle($request->user('tenant')->id); return back()->with('status','Follower preference updated.');
    }
}
