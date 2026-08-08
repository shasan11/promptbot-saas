<?php

namespace App\Enums\Knowledge;

enum ProcessingJobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Retrying = 'retrying';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Retrying => 'Retrying',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running, self::Retrying], true);
    }
}
