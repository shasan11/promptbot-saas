<?php

namespace App\Enums\Knowledge;

enum KnowledgeBaseStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Processing = 'processing';
    case Warning = 'warning';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Processing => 'Processing',
            self::Warning => 'Needs attention',
            self::Disabled => 'Disabled',
            self::Archived => 'Archived',
        };
    }

    /**
     * Only these states may serve chunks to retrieval. Draft bases are still
     * being assembled, disabled/archived ones were intentionally taken out of
     * service — a warning base still answers, it just has failing sources.
     */
    public function isRetrievable(): bool
    {
        return in_array($this, [self::Active, self::Processing, self::Warning], true);
    }
}
