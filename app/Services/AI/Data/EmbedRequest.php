<?php

namespace App\Services\AI\Data;

use App\Enums\AI\AIPurpose;

final class EmbedRequest
{
    /** @param  array<int, string>  $texts */
    public function __construct(
        public readonly array $texts,
        public readonly AIPurpose $purpose = AIPurpose::KnowledgeEmbedding,
    ) {}
}
