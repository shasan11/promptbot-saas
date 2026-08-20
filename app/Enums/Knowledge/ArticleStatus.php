<?php

namespace App\Enums\Knowledge;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /** Only published articles are embedded and served to agents. */
    public function isRetrievable(): bool
    {
        return $this === self::Published;
    }

    /**
     * Guards every status change made by the controller. Draft content must
     * never accidentally reach an AI agent, so the only way into Published is
     * through InReview — there is no direct Draft -> Published transition.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview, self::Archived],
            self::InReview => [self::Draft, self::Published],
            self::Published => [self::Archived],
            self::Archived => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
