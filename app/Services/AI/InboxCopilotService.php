<?php

namespace App\Services\AI;

use App\Models\AI\Agent;
use App\Models\AI\ConversationInsight;
use App\Models\AI\Run;
use App\Models\AI\Suggestion;
use App\Models\Inbox\Conversation;
use App\Models\User;
use App\Neuron\Output\ConversationClassificationOutput;
use App\Services\Knowledge\QueryRewriteService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InboxCopilotService
{
    public function __construct(private readonly TenantAgentRuntime $runtime) {}

    /** @return array<string, mixed> */
    public function perform(Conversation $conversation, string $operation, ?User $actor = null, array $options = [], ?Agent $agent = null): array
    {
        $agent ??= $this->agentFor($conversation); $transcript = $this->transcript($conversation, $agent);
        if ($operation === 'classify') return $this->classify($conversation, $agent, $transcript, $actor);
        $request = match ($operation) {
            'summary' => 'Summarize the customer goal, key facts, actions already taken, unresolved questions, and current state. Do not add facts.',
            'draft' => 'Draft a concise support reply to the customer. Answer factual questions only from permitted workspace knowledge. Do not claim any action was performed. Include citations for grounded factual claims.',
            'rewrite' => 'Rewrite the following draft for clarity and a professional, friendly tone while preserving every fact: '.($options['text'] ?? ''),
            'translate' => 'Translate the following draft into '.($options['language'] ?? 'English').'. Preserve meaning, names, numbers, URLs, and citations: '.($options['text'] ?? ''),
            'suggest' => 'Suggest up to three safe next steps for a human support agent. Separate recommendations from completed actions and explain why.',
            default => throw ValidationException::withMessages(['operation' => 'Unsupported copilot operation.']),
        };
        $ground = $operation === 'draft';
        $result = $this->runtime->chat(
            $agent,
            $request."\n\nConversation transcript:\n".$transcript,
            $actor,
            'inbox_copilot',
            $operation,
            $ground,
            // Retrieval searches for the customer's question; the model still
            // answers the full prompt. Passing the whole prompt to both meant
            // the search vector was mostly instruction text and transcript
            // history, and any resulting knowledge gap was filed under the
            // prompt rather than under what the customer actually asked.
            retrievalQuery: $ground ? $this->retrievalQuestion($conversation) : null,
        );
        $suggestion = Suggestion::query()->where('public_uuid', $result['suggestion_uuid'])->firstOrFail();
        $suggestion->forceFill(['resource_type' => Conversation::class, 'resource_id' => $conversation->id, 'conversation_id' => $conversation->id, 'message_id' => $conversation->messages()->latest()->value('id'), 'type' => $operation])->save();
        $this->attachRunToConversation($result['run_uuid'] ?? null, $conversation);
        if ($operation === 'summary') ConversationInsight::query()->updateOrCreate(['conversation_id' => $conversation->id], ['summary' => $result['text'], 'summary_generated_at' => now(), 'summary_until_message_id' => $conversation->messages()->latest()->value('id'), 'agent_id' => $agent->id, 'ai_run_id' => Run::query()->where('public_uuid', $result['run_uuid'])->value('id')]);
        return $result + ['operation' => $operation];
    }

    public function classify(Conversation $conversation, ?Agent $agent = null, ?string $transcript = null, ?User $actor = null): array
    {
        $agent ??= $this->agentFor($conversation); $transcript ??= $this->transcript($conversation, $agent);
        $result = $this->runtime->structured($agent, $transcript, ConversationClassificationOutput::class, $actor);
        $data = $result['data'];
        $priority = in_array($data['suggestedPriority'] ?? null, ['low','normal','high','urgent'], true) ? $data['suggestedPriority'] : null;
        ConversationInsight::query()->updateOrCreate(['conversation_id' => $conversation->id], [
            'intent' => Str::limit((string) ($data['intent'] ?? 'unknown'), 48, ''),
            'sentiment' => in_array($data['sentiment'] ?? null, ['positive','neutral','negative','mixed'], true) ? $data['sentiment'] : 'neutral',
            'urgency' => in_array($data['urgency'] ?? null, ['low','normal','high','urgent'], true) ? $data['urgency'] : 'normal',
            'language' => Str::limit((string) ($data['language'] ?? ''), 16, ''), 'suggested_priority' => $priority,
            'risk_flags' => array_slice(array_map('strval', (array) ($data['riskFlags'] ?? [])), 0, 10),
            'last_message_id' => $conversation->messages()->latest()->value('id'), 'classified_at' => now(),
            'agent_id' => $agent->id, 'ai_run_id' => Run::query()->where('public_uuid', $result['run_uuid'])->value('id'),
        ]);
        $this->attachRunToConversation($result['run_uuid'] ?? null, $conversation);

        return $result + ['operation' => 'classify'];
    }

    /**
     * The customer's latest question, expanded with the conversation's
     * established topic when it cannot stand alone ("how much does it cost?").
     *
     * Returns null when there is no inbound message to search for, which puts
     * the runtime back on its default of searching the full prompt rather than
     * searching nothing.
     */
    private function retrievalQuestion(Conversation $conversation): ?string
    {
        $question = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest('id')
            ->value('body');

        if (! filled($question)) {
            return null;
        }

        return app(QueryRewriteService::class)->rewrite((string) $question, $conversation) ?: null;
    }

    /**
     * Links the run to the conversation it was made for.
     *
     * `ai_runs.conversation_id` has existed since the AI platform tables were
     * created and nothing ever wrote to it — the runtime does not know what a
     * conversation is, and this service, which does, only ever set the id on
     * the resulting suggestion. That left every per-conversation question
     * about AI ("what did answering this cost?", "how many model calls did it
     * take?") unanswerable from the runs table. Backfilling the column here
     * costs one update per run and is the smallest change that makes it true.
     */
    private function attachRunToConversation(?string $runUuid, Conversation $conversation): void
    {
        if (! $runUuid) {
            return;
        }

        Run::query()->where('public_uuid', $runUuid)->update([
            'conversation_id' => $conversation->id,
            'message_id' => $conversation->messages()->latest()->value('id'),
        ]);
    }

    public function agentFor(Conversation $conversation): Agent
    {
        $agent = Agent::query()->where('status', 'active')->whereHas('channels', fn ($query) => $query->where('channels.id', $conversation->channel_id)->where('ai_agent_channels.enabled', true))->first()
            ?? Agent::query()->where('status', 'active')->orderBy('id')->first();
        if (! $agent) throw ValidationException::withMessages(['agent' => 'Deploy an AI agent before using the inbox copilot.']);
        return $agent;
    }

    private function transcript(Conversation $conversation, Agent $agent): string
    {
        $insight = $conversation->aiInsight()->first();
        $limit = $agent->memory_enabled ? 30 : 10;
        $query = $conversation->messages()->latest();
        if ($agent->memory_enabled && $agent->memory_strategy === 'recent_with_summary' && $insight?->summary_until_message_id) {
            $query->where('id', '>', $insight->summary_until_message_id);
        }
        $recent = $query->limit($limit)->get()->reverse()->map(fn ($message) => strtoupper($message->direction)." | ".($message->sender_name ?: 'Unknown').": ".Str::limit(strip_tags((string) $message->body), 5000))->implode("\n---\n");
        if ($agent->memory_enabled && $agent->memory_strategy === 'recent_with_summary' && filled($insight?->summary)) {
            return "<UNTRUSTED-HISTORICAL-SUMMARY>\n{$insight->summary}\n</UNTRUSTED-HISTORICAL-SUMMARY>\n\n<RECENT-MESSAGES>\n{$recent}\n</RECENT-MESSAGES>";
        }
        return $recent;
    }
}
