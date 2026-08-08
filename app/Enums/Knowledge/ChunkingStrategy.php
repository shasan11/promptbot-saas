<?php

namespace App\Enums\Knowledge;

enum ChunkingStrategy: string
{
    case FixedToken = 'fixed_token';
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case Semantic = 'semantic';
    case Faq = 'faq';
    case Markdown = 'markdown';
    case Code = 'code';

    public function label(): string
    {
        return match ($this) {
            self::FixedToken => 'Fixed size',
            self::Paragraph => 'Paragraph-aware',
            self::Heading => 'Heading-aware',
            self::Semantic => 'Semantic',
            self::Faq => 'Question & answer',
            self::Markdown => 'Markdown-aware',
            self::Code => 'Code-aware',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FixedToken => 'Splits text into equally sized windows. Predictable and fast; ignores document structure.',
            self::Paragraph => 'Keeps paragraphs intact and packs them up to the chunk size. A good default for prose.',
            self::Heading => 'Starts a new chunk at every heading so each chunk stays on one topic. Best for manuals and policies.',
            self::Semantic => 'Groups adjacent sentences that talk about the same thing. Slower, but produces the tightest chunks.',
            self::Faq => 'Treats each question and its answer as one chunk. Used automatically for FAQ sources.',
            self::Markdown => 'Respects Markdown headings, lists, tables and fenced code blocks.',
            self::Code => 'Splits on function and class boundaries rather than mid-statement.',
        };
    }
}
