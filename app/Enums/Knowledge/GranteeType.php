<?php

namespace App\Enums\Knowledge;

/**
 * Who an access grant is for. `Agent` is intentionally a loose string reference
 * rather than a foreign key: PromptBot's AI Agents module does not exist yet, so
 * grants store the future agent identifier and retrieval already enforces them.
 * When the agents table lands, only KnowledgePermissionService::resolveAgent()
 * needs to change.
 */
enum GranteeType: string
{
    case User = 'user';
    case Team = 'team';
    case Role = 'role';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Team => 'Team',
            self::Role => 'Role',
            self::Agent => 'AI agent',
        };
    }

    /** Grantee types resolved against a real tenant table. */
    public function isPersisted(): bool
    {
        return $this !== self::Agent;
    }
}
