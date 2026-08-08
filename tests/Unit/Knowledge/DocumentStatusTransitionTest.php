<?php

namespace Tests\Unit\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use Tests\TestCase;

class DocumentStatusTransitionTest extends TestCase
{
    public function test_a_ready_document_cannot_be_reset_to_uploaded(): void
    {
        // The transition the state machine exists to prevent: a stray worker
        // rewinding indexed content to the start of the pipeline.
        $this->assertFalse(DocumentStatus::Ready->canTransitionTo(DocumentStatus::Uploaded));
        $this->assertFalse(DocumentStatus::Ready->canTransitionTo(DocumentStatus::Extracting));
    }

    public function test_a_failed_document_re_enters_only_at_the_top(): void
    {
        $this->assertTrue(DocumentStatus::Failed->canTransitionTo(DocumentStatus::Queued));

        // Resuming mid-pipeline would chunk against text that was never extracted.
        $this->assertFalse(DocumentStatus::Failed->canTransitionTo(DocumentStatus::Chunking));
        $this->assertFalse(DocumentStatus::Failed->canTransitionTo(DocumentStatus::Ready));
    }

    public function test_the_happy_path_is_fully_connected(): void
    {
        $path = [
            DocumentStatus::Uploaded, DocumentStatus::Queued, DocumentStatus::Validating,
            DocumentStatus::Extracting, DocumentStatus::Processing, DocumentStatus::Chunking,
            DocumentStatus::Embedding, DocumentStatus::Indexing, DocumentStatus::Ready,
        ];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue(
                $path[$i]->canTransitionTo($path[$i + 1]),
                "Pipeline broken: {$path[$i]->value} cannot reach {$path[$i + 1]->value}"
            );
        }
    }

    public function test_every_stage_can_fail(): void
    {
        foreach ([
            DocumentStatus::Validating, DocumentStatus::Extracting, DocumentStatus::Processing,
            DocumentStatus::Chunking, DocumentStatus::Embedding, DocumentStatus::Indexing,
        ] as $status) {
            $this->assertTrue($status->canTransitionTo(DocumentStatus::Failed), "{$status->value} has no failure path");
        }
    }

    public function test_only_ready_states_serve_retrieval(): void
    {
        $retrievable = array_filter(DocumentStatus::cases(), fn (DocumentStatus $s) => $s->isRetrievable());

        $this->assertEqualsCanonicalizing(
            [DocumentStatus::Ready, DocumentStatus::PartiallyReady],
            array_values($retrievable),
            'A non-ready status became retrievable — half-processed content would start answering questions.'
        );
    }

    public function test_archived_and_outdated_content_is_not_retrievable(): void
    {
        $this->assertFalse(DocumentStatus::Archived->isRetrievable());
        $this->assertFalse(DocumentStatus::Outdated->isRetrievable());
        $this->assertFalse(DocumentStatus::Failed->isRetrievable());
    }
}
