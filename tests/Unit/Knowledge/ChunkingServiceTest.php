<?php

namespace Tests\Unit\Knowledge;

use App\Enums\Knowledge\ChunkingStrategy;
use App\Services\Knowledge\ChunkingService;
use App\Services\Knowledge\Data\ChunkCandidate;
use App\Services\Knowledge\Data\ExtractedContent;
use Tests\TestCase;

class ChunkingServiceTest extends TestCase
{
    private ChunkingService $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunker = new ChunkingService;
    }

    public function test_it_assigns_sequential_chunk_indexes(): void
    {
        $content = new ExtractedContent(str_repeat('The refund window is thirty days. ', 400));

        $chunks = $this->chunker->chunk($content, ChunkingStrategy::FixedToken, 128, 16);

        $this->assertGreaterThan(1, count($chunks));

        // Indexes back the (owner_key, chunk_index) unique constraint. A gap or
        // a duplicate here becomes an integrity violation at insert time.
        foreach ($chunks as $position => $chunk) {
            $this->assertSame($position, $chunk->index);
        }
    }

    public function test_overlap_never_prevents_the_window_advancing(): void
    {
        // An overlap larger than the chunk size would make a naive sliding
        // window loop forever. The service clamps it instead.
        $content = new ExtractedContent(str_repeat('word ', 2000));

        $chunks = $this->chunker->chunk($content, ChunkingStrategy::FixedToken, 100, 5000);

        $this->assertNotEmpty($chunks);
        $this->assertLessThan(500, count($chunks), 'Clamping failed — the window barely advanced.');
    }

    public function test_heading_aware_chunking_keeps_sections_apart(): void
    {
        $content = new ExtractedContent(
            "Refunds\nThirty days.\n\nShipping\nForty-two countries.",
            segments: [
                ['text' => 'Customers may request a refund within thirty days.', 'heading' => 'Refunds', 'type' => 'paragraph'],
                ['text' => 'We ship to forty-two countries worldwide.', 'heading' => 'Shipping', 'type' => 'paragraph'],
            ],
        );

        $chunks = $this->chunker->chunk($content, ChunkingStrategy::Heading, 512, 0);

        // Two topics must not share an embedding, however small they are.
        $this->assertCount(2, $chunks);
        $this->assertSame('Refunds', $chunks[0]->metadata['heading']);
        $this->assertSame('Shipping', $chunks[1]->metadata['heading']);
    }

    public function test_page_numbers_survive_into_chunk_metadata(): void
    {
        $content = new ExtractedContent(
            'Page one text. Page two text.',
            segments: [
                ['text' => 'Refund requests are processed within five business days.', 'page' => 1, 'type' => 'page'],
                ['text' => 'Shipping is free above one hundred dollars.', 'page' => 4, 'type' => 'page'],
            ],
        );

        $chunks = $this->chunker->chunk($content, ChunkingStrategy::Heading, 512, 0);

        // Without this, a PDF citation cannot say "page 4".
        $this->assertSame(1, $chunks[0]->metadata['page']);
        $this->assertSame(4, $chunks[1]->metadata['page']);
    }

    public function test_faq_strategy_keeps_question_and_answer_together(): void
    {
        $content = new ExtractedContent("Q: How long is the refund period?\n\nA: Thirty days from purchase.");

        $chunks = $this->chunker->chunk($content, ChunkingStrategy::Faq, 512, 64);

        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('How long is the refund period?', $chunks[0]->content);
        $this->assertStringContainsString('Thirty days from purchase.', $chunks[0]->content);
    }

    public function test_empty_content_produces_no_chunks(): void
    {
        $this->assertSame([], $this->chunker->chunk(new ExtractedContent('   '), ChunkingStrategy::Paragraph, 512, 64));
    }

    public function test_chunk_hash_ignores_whitespace_differences(): void
    {
        // Change detection depends on this: a re-crawl returning the same prose
        // with different wrapping must not trigger a paid re-embed.
        $a = new ChunkCandidate(0, "The refund window   is\n\nthirty days.");
        $b = new ChunkCandidate(0, 'The refund window is thirty days.');

        $this->assertSame($a->hash(), $b->hash());
    }

    public function test_chunk_hash_differs_when_content_differs(): void
    {
        $a = new ChunkCandidate(0, 'The refund window is thirty days.');
        $b = new ChunkCandidate(0, 'The refund window is sixty days.');

        $this->assertNotSame($a->hash(), $b->hash());
    }

    public function test_oversized_paragraphs_are_split_rather_than_dropped(): void
    {
        $long = str_repeat('This sentence is part of an extremely long paragraph. ', 300);
        $content = new ExtractedContent("Short intro.\n\n{$long}");

        $chunks = $this->chunker->chunk($content, ChunkingStrategy::Paragraph, 128, 16);

        $this->assertGreaterThan(2, count($chunks));

        $reassembled = implode(' ', array_map(fn (ChunkCandidate $c) => $c->content, $chunks));
        $this->assertStringContainsString('Short intro.', $reassembled);
    }
}
