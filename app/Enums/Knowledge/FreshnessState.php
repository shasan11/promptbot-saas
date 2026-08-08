<?php

namespace App\Enums\Knowledge;

enum FreshnessState: string
{
    case Current = 'current';
    case PotentiallyOutdated = 'potentially_outdated';
    case Outdated = 'outdated';
    case Disconnected = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::PotentiallyOutdated => 'Review due soon',
            self::Outdated => 'Outdated',
            self::Disconnected => 'Disconnected',
        };
    }

    /**
     * Derives freshness from a source's review window. `$reviewEveryDays` is the
     * tenant-configured expectation; content is flagged for review at 80% of it
     * so admins get warned before knowledge actually goes stale.
     */
    public static function forAge(?int $ageInDays, ?int $reviewEveryDays): self
    {
        if ($ageInDays === null || $reviewEveryDays === null || $reviewEveryDays <= 0) {
            return self::Current;
        }

        return match (true) {
            $ageInDays >= $reviewEveryDays => self::Outdated,
            $ageInDays >= (int) floor($reviewEveryDays * 0.8) => self::PotentiallyOutdated,
            default => self::Current,
        };
    }
}
