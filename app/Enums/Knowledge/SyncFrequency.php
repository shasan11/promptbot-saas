<?php

namespace App\Enums\Knowledge;

use Carbon\CarbonInterface;

enum SyncFrequency: string
{
    case Manual = 'manual';
    case Hourly = 'hourly';
    case SixHourly = 'six_hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual only',
            self::Hourly => 'Every hour',
            self::SixHourly => 'Every 6 hours',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }

    public function intervalMinutes(): ?int
    {
        return match ($this) {
            self::Manual => null,
            self::Hourly => 60,
            self::SixHourly => 360,
            self::Daily => 1440,
            self::Weekly => 10080,
        };
    }

    public function nextRunAfter(CarbonInterface $from): ?CarbonInterface
    {
        $minutes = $this->intervalMinutes();

        return $minutes === null ? null : $from->copy()->addMinutes($minutes);
    }
}
