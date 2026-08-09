<?php

namespace App\Services\Inbox;

use App\Events\Inbox\ConversationReceived;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Models\Inbox\Conversation;
use App\Models\Inbox\ConversationAssignment;
use App\Models\Inbox\Message;
use App\Models\Inbox\MessageMention;
use App\Models\User;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Customer\CustomerTimelineService;
use App\Services\Operations\RoutingService;
use App\Services\Sla\SlaService;
use App\Services\Automation\AutomationExecutionService;
use App\Services\Developer\OutboundWebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(private readonly ChannelManager $channels, private readonly CustomerTimelineService $timeline, private readonly RoutingService $routing, private readonly SlaService $sla, private readonly AutomationExecutionService $automation, private readonly OutboundWebhookService $webhooks) {}

    public function receive(Channel $channel, Contact $contact, array $payload): Message
    {
        return DB::transaction(function () use ($channel, $contact, $payload): Message {
            $externalId = $payload['external_id'] ?? null;
            if ($externalId && ($existing = Message::query()->whereHas('conversation', fn ($q) => $q->where('channel_id', $channel->id))->where('channel_message_id', $externalId)->first())) return $existing;
            $conversation = $this->resolveConversation($channel, $contact, $payload);
            if ($conversation->wasRecentlyCreated) { $this->routing->route($conversation); $this->sla->start($conversation); $this->automation->dispatch('conversation.created', $conversation); $this->webhooks->record('conversation.created', $conversation); }
            $message = $conversation->messages()->create(['sender_type' => 'contact', 'sender_id' => $contact->id, 'sender_name' => $contact->display_name, 'direction' => 'inbound', 'message_type' => $payload['message_type'] ?? ($channel->type === 'email' ? 'email' : 'text'), 'body' => trim((string) ($payload['body'] ?? '')), 'body_html' => null, 'channel_message_id' => $externalId, 'status' => 'received', 'sent_at' => $payload['sent_at'] ?? now(), 'metadata' => $payload['metadata'] ?? null]);
            $now = now(); $conversation->update(['first_message_at' => $conversation->first_message_at ?? $now, 'last_message_at' => $now, 'message_count' => DB::raw('message_count + 1'), 'unread_count' => DB::raw('unread_count + 1'), 'status' => in_array($conversation->status, ['closed', 'resolved', 'snoozed'], true) ? 'open' : $conversation->status, 'snoozed_until' => null]);
            $channel->update(['last_activity_at' => $now]); $contact->update(['last_contacted_at' => $now, 'last_seen_at' => $now]);
            $this->timeline->record('message.incoming', "Incoming {$channel->type} message received.", $contact, related: $conversation, metadata: ['message_id' => $message->public_uuid]);
            $this->automation->dispatch('message.received', $conversation->fresh());
            $this->webhooks->record('message.received', $conversation, ['message_id' => $message->public_uuid]);
            DB::afterCommit(fn () => ConversationReceived::dispatch($conversation, $message));
            return $message;
        });
    }

    public function reply(Conversation $conversation, User $actor, array $data): Message
    {
        $internal = ($data['message_type'] ?? 'text') === 'note';
        $message = DB::transaction(function () use ($conversation, $actor, $data, $internal): Message {
            $message = $conversation->messages()->create(['sender_type' => 'user', 'sender_id' => $actor->id, 'sender_name' => $actor->name, 'direction' => $internal ? 'internal' : 'outbound', 'message_type' => $internal ? 'note' : ($conversation->channel->type === 'email' ? 'email' : 'text'), 'body' => trim($data['body']), 'status' => $internal ? 'delivered' : 'pending', 'sent_at' => now()]);
            $this->recordMentions($message);
            $conversation->update(['last_message_at' => now(), 'message_count' => DB::raw('message_count + 1'), 'first_response_at' => $internal ? $conversation->first_response_at : ($conversation->first_response_at ?? now()), 'unread_count' => 0]);
            return $message;
        });
        if (! $internal) {
            $channel = $conversation->channel; $recipient = $conversation->contact->email ?: $conversation->contact->phone;
            $result = $this->channels->adapter($channel->type)->send($channel, new OutboundMessage($recipient, $conversation->subject ?: 'Support conversation', $message->body, headers: ['X-PromptBot-Conversation' => $conversation->public_uuid]));
            $message->update($result->successful ? ['status' => 'sent', 'delivered_at' => now(), 'channel_message_id' => $result->providerMessageId] : ['status' => 'failed', 'failed_at' => now(), 'failure_reason' => $result->error]);
            if ($result->successful) { $this->timeline->record('message.outgoing', "Reply sent by {$actor->name}.", $conversation->contact, actor: $actor, related: $conversation); $this->sla->fulfillFirstResponse($conversation); $this->automation->dispatch('message.sent', $conversation->fresh()); $this->webhooks->record('message.sent', $conversation, ['message_id' => $message->public_uuid]); }
        }
        return $message;
    }

    public function assign(Conversation $conversation, ?int $userId, ?int $teamId, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($conversation, $userId, $teamId, $actor, $reason): void {
            ConversationAssignment::create(['conversation_id' => $conversation->id, 'from_user_id' => $conversation->assignee_id, 'to_user_id' => $userId, 'from_team_id' => $conversation->team_id, 'to_team_id' => $teamId, 'assigned_by' => $actor->id, 'reason' => $reason]);
            $conversation->update(['assignee_id' => $userId, 'team_id' => $teamId]);
            $this->timeline->record('assignment.changed', 'Conversation assignment changed.', $conversation->contact, actor: $actor, related: $conversation, metadata: ['user_id' => $userId, 'team_id' => $teamId]);
        });
    }

    private function resolveConversation(Channel $channel, Contact $contact, array $payload): Conversation
    {
        $thread = $payload['thread_reference'] ?? null;
        $conversation = Conversation::query()->where('channel_id', $channel->id)->where('contact_id', $contact->id)
            ->when($thread, fn ($q) => $q->where('external_reference', $thread), fn ($q) => $q->whereNotIn('status', ['closed'])->where('last_message_at', '>=', now()->subDays(7)))
            ->latest('last_message_at')->lockForUpdate()->first();
        if ($conversation) return $conversation;
        $conversation = Conversation::create(['contact_id' => $contact->id, 'company_id' => $contact->company_id, 'channel_id' => $channel->id, 'team_id' => $channel->team_id, 'assignee_id' => $channel->default_assignee_id, 'status' => 'open', 'priority' => 'normal', 'subject' => $payload['subject'] ?? null, 'external_reference' => $thread]);
        $this->timeline->record('conversation.opened', 'Conversation opened.', $contact, related: $conversation);
        return $conversation;
    }

    private function recordMentions(Message $message): void
    {
        preg_match_all('/@([a-zA-Z0-9._-]+)/', $message->body, $matches);
        foreach (array_unique($matches[1] ?? []) as $handle) {
            $user = User::query()->whereRaw("LOWER(REPLACE(name, ' ', '.')) = ?", [Str::lower($handle)])->orWhere('email', 'like', $handle.'@%')->first();
            if ($user) MessageMention::firstOrCreate(['message_id' => $message->id, 'user_id' => $user->id]);
        }
    }
}
