<?php

namespace App\Enums\Knowledge;

enum SourceStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case PartiallyReady = 'partially_ready';
    case AttentionRequired = 'attention_required';
    case Failed = 'failed';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Ready => 'Ready',
            self::PartiallyReady => 'Partially ready',
            self::AttentionRequired => 'Needs attention',
            self::Failed => 'Failed',
            self::Disabled => 'Disabled',
            self::Archived => 'Archived',
        };
    }

    public function isRetrievable(): bool
    {
        return in_array($this, [self::Ready, self::PartiallyReady, self::Processing, self::AttentionRequired], true);
    }

    /** Buckets used by the overview "Knowledge health" widget. */
    public function healthBucket(): string
    {
        return match ($this) {
            self::Ready => 'healthy',
            self::Pending, self::Queued, self::Processing => 'processing',
            self::PartiallyReady, self::AttentionRequired => 'needs_attention',
            self::Failed => 'failed',
            self::Disabled, self::Archived => 'inactive',
        };
    }
}
