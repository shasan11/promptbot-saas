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
            temperature: 0.2,
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
            You are a support assistant answering strictly from the provided context.
            Rules:
            - Only use facts present in the context. Never invent information.
            - If the context does not contain the answer, say so plainly.
            - Cite sources inline using [1], [2], etc., matching the order excerpts appear in the context.
            - Be concise and direct.
            PROMPT;
    }
}
