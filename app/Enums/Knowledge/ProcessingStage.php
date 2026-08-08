<?php

namespace App\Enums\Knowledge;

/**
 * Granular pipeline stages, recorded per attempt in `knowledge_processing_logs`.
 * Distinct from DocumentStatus: status is the item's current resting state,
 * stage is what a worker is doing (or was doing when it failed) right now.
 */
enum ProcessingStage: string
{
    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case Scanning = 'scanning';
    case Extracting = 'extracting';
    case Normalizing = 'normalizing';
    case DetectingLanguage = 'detecting_language';
    case Deduplicating = 'deduplicating';
    case Chunking = 'chunking';
    case Embedding = 'embedding';
    case Indexing = 'indexing';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Validating => 'Validating',
            self::Scanning => 'Scanning for threats',
            self::Extracting => 'Extracting text',
            self::Normalizing => 'Normalizing content',
            self::DetectingLanguage => 'Detecting language',
            self::Deduplicating => 'Checking for duplicates',
            self::Chunking => 'Splitting into chunks',
            self::Embedding => 'Generating embeddings',
            self::Indexing => 'Indexing',
            self::Ready => 'Ready',
        };
    }

    /** Ordered pipeline, used to render stage progress bars. */
    public static function pipeline(): array
    {
        return [
            self::Uploaded, self::Validating, self::Scanning, self::Extracting,
            self::Normalizing, self::DetectingLanguage, self::Deduplicating,
            self::Chunking, self::Embedding, self::Indexing, self::Ready,
        ];
    }

    public function position(): int
    {
        return (int) array_search($this, self::pipeline(), true);
    }
}
