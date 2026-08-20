<?php

namespace Tests\Unit\Knowledge;

use App\Enums\Knowledge\ArticleStatus;
use Tests\TestCase;

class ArticleStatusTransitionTest extends TestCase
{
    public function test_a_draft_cannot_jump_straight_to_published(): void
    {
        // The transition the state machine exists to prevent: unapproved
        // content becoming AI-answerable without a reviewer in the loop.
        $this->assertFalse(ArticleStatus::Draft->canTransitionTo(ArticleStatus::Published));
    }

    public function test_review_can_go_either_way(): void
    {
        $this->assertTrue(ArticleStatus::InReview->canTransitionTo(ArticleStatus::Published));
        $this->assertTrue(ArticleStatus::InReview->canTransitionTo(ArticleStatus::Draft));
    }

    public function test_a_published_article_can_only_be_archived(): void
    {
        $this->assertTrue(ArticleStatus::Published->canTransitionTo(ArticleStatus::Archived));
        $this->assertFalse(ArticleStatus::Published->canTransitionTo(ArticleStatus::Draft));
        $this->assertFalse(ArticleStatus::Published->canTransitionTo(ArticleStatus::InReview));
    }

    public function test_restoring_an_archived_article_lands_on_draft_not_published(): void
    {
        // An archived article must be re-reviewed before it can answer
        // questions again — restore must not silently reopen retrieval.
        $this->assertTrue(ArticleStatus::Archived->canTransitionTo(ArticleStatus::Draft));
        $this->assertFalse(ArticleStatus::Archived->canTransitionTo(ArticleStatus::Published));
    }

    public function test_only_published_is_retrievable(): void
    {
        $retrievable = array_filter(ArticleStatus::cases(), fn (ArticleStatus $s) => $s->isRetrievable());

        $this->assertEqualsCanonicalizing([ArticleStatus::Published], array_values($retrievable));
    }
}
