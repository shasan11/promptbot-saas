<?php

namespace App\Enums\Knowledge;

enum FaqStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /** Only published FAQs are embedded and served to agents. */
    public function isRetrievable(): bool
    {
        return $this === self::Published;
    }
}
