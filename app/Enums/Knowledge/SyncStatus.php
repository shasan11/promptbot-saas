<?php

namespace App\Enums\Knowledge;

enum SyncStatus: string
{
    case NeverSynced = 'never_synced';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NeverSynced => 'Never synced',
            self::Queued => 'Queued',
            self::Running => 'Syncing',
            self::Completed => 'Synced',
            self::CompletedWithErrors => 'Synced with errors',
            self::Failed => 'Sync failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors, self::Failed, self::Cancelled], true);
    }
}
