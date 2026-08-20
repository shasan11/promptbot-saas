<?php

namespace App\Services\Knowledge;

use App\Enums\AI\AIPurpose;
use App\Services\AI\AIFeatureManager;
use App\Services\AI\AIManager;
use App\Services\AI\Data\ChatMessage;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Exceptions\AIException;
use App\Services\Knowledge\Data\RetrievalHit;
use App\Services\Knowledge\Data\RetrievalOutcome;

/**
 * Turns a retrieval outcome into an answer for the human asking the question.
 *
 * Real generation (a chat-model call grounded in the retrieved context) is
 * used whenever the platform has AI enabled, the `knowledge_answers` feature
 * is on, and — for tenant-originated requests — the platform owner has
 * allowed tenant AI access. Otherwise this degrades to a purely extractive
 * preview stitched from the top chunks, which needs no AI configuration at
 * all. Callers never see the difference in shape, only `generated_by`.
 */
class KnowledgeAnswerService
{
    private const FEATURE_KEY = 'knowledge_answers';

    public function __construct(
        private readonly AIManager $ai,
        private readonly AIFeatureManager $features,
    ) {}

    /** @return array<string, mixed> */
    public function answer(RetrievalOutcome $outcome, string $question): array
    {
        if ($outcome->isEmpty()) {
            return [
                'answer' => 'I could not find enough matching knowledge to answer this safely.',
                'confidence' => 'low',
                'sources_used' => [],
                'generated_by' => 'extractive',
            ];
        }

        if ($this->features->isEnabled(self::FEATURE_KEY)) {
            try {
                return $this->generate($outcome, $question);
            } catch (AIException $exception) {
                report($exception);
                // Fall through to the extractive preview — a broken provider
                // must never take the whole playground/answer surface down.
            }
        }

        return $this->extractive($outcome, $this->features->isEnabled(self::FEATURE_KEY)
            ? 'AI generation failed; showing an extractive preview instead.'
            : 'Preview is grounded only in retrieved excerpts and does not call a chat model.');
    }

    /** @return array<string, mixed> */
    private function generate(RetrievalOutcome $outcome, string $question): array
    {
        $result = $this->ai->forPurpose(AIPurpose::RagAnswer)->chat(new ChatRequest(
            messages: [
                new ChatMessage('system', $this->systemPrompt()),
                new ChatMessage('user', "Context:\n{$outcome->context}\n\nQuestion: {$question}"),
            ],
            // 0.2 read as near-extractive in practice — the model leaned on
            // copying the context's own wording instead of paraphrasing it.
            // 0.4 gives it room to write a natural sentence while the system
            // prompt's explicit "never invent" / "cite everything" rules keep
            // it grounded; the risk knob here is phrasing, not facts.
            temperature: 0.4,
            maxTokens: 700,
        ));

        return [
            'answer' => trim($result->content),
            'confidence' => $this->confidenceFor($outcome),
            'sources_used' => $outcome->citations(),
            'generated_by' => 'ai',
            'model' => $result->model,
        ];
    }

    /** @return array<string, mixed> */
    private function extractive(RetrievalOutcome $outcome, string $note): array
    {
        $sentences = [];
        $sources = [];

        foreach (array_slice($outcome->hits, 0, 3) as $index => $hit) {
            /** @var RetrievalHit $hit */
            $citation = $hit->chunk->citation();
            $sources[] = array_merge($citation, ['rank' => $index + 1, 'score' => round($hit->finalScore, 5)]);

            $text = trim(preg_replace('/\s+/', ' ', $hit->chunk->content) ?? '');
            $firstSentence = preg_split('/(?<=[.!?])\s+/', $text, 2)[0] ?? $text;

            if ($firstSentence !== '') {
                $sentences[] = rtrim($firstSentence, '.').' ['.($index + 1).']';
            }
        }

        return [
            'answer' => $sentences
                ? implode(' ', $sentences)
                : 'The retrieved sources matched, but there was no concise text to preview.',
            'confidence' => $this->confidenceFor($outcome),
            'sources_used' => $sources,
            'generated_by' => 'extractive',
            'note' => $note,
        ];
    }

    private function confidenceFor(RetrievalOutcome $outcome): string
    {
        $topScore = $outcome->topScore() ?? 0.0;

        return $topScore >= 0.75 ? 'high' : ($topScore >= 0.45 ? 'medium' : 'low');
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a warm, professional customer support agent. You are talking
            directly to a customer, not summarizing a document for a colleague.

            How to write:
            - Answer in your own natural words. Never copy or paste sentences
              verbatim from the reference material below, and never reproduce its
              headings, titles, version numbers, or any other document formatting
              — the customer should never see anything that reveals the answer
              came from a document at all.
            - Write like you would speak: plain sentences, a friendly and concise
              tone, no bullet-dump of everything the reference material says.
              Answer only what was asked.
            - Keep it short. One or two sentences for a simple question. Use a
              short list only when the question genuinely calls for one (e.g.
              "what are my options").

            What you may claim:
            - Only state facts that are actually supported by the reference
              material. Never invent details, prices, policies, dates, or
              capabilities.
            - If the reference material does not answer the question, say so
              plainly and suggest the customer wait for a human teammate — do not
              guess or pad the answer with unrelated material.

            Citations:
            - Cite the source of each claim inline using [1], [2], etc., matching
              the order excerpts appear in the reference material. Citations
              support the sentence; they are not the sentence.

            Never mention "the context," "the provided material," "excerpts," or
            any other system/internal detail — just answer like a person who
            already knows the answer.
            PROMPT;
    }
}
