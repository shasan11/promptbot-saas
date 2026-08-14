<?php

namespace App\Enums\AI;

enum AIPurpose: string
{
    case General = 'general';
    case ChatCompletion = 'chat_completion';
    case KnowledgeEmbedding = 'knowledge_embedding';
    case RagAnswer = 'rag_answer';
    case InboxReplyDraft = 'inbox_reply_draft';
    case ConversationSummary = 'conversation_summary';
    case AutomationAction = 'automation_action';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::ChatCompletion => 'Chat Completion',
            self::KnowledgeEmbedding => 'Knowledge / RAG Embedding',
            self::RagAnswer => 'Knowledge / RAG Answer Generation',
            self::InboxReplyDraft => 'Inbox Reply Draft',
            self::ConversationSummary => 'Conversation Summary',
            self::AutomationAction => 'Automation Action',
        };
    }

    public function capability(): AIModelCapability
    {
        return $this === self::KnowledgeEmbedding ? AIModelCapability::Embedding : AIModelCapability::Chat;
    }
}
