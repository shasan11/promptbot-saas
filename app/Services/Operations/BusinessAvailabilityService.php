<?php

namespace App\Services\Operations;

use App\Models\BusinessHourInterval;
use App\Models\BusinessHourPolicy;
use App\Models\Holiday;
use Carbon\CarbonImmutable;

/**
 * Answers one question: is this workspace open for business right now?
 *
 * `BusinessTimeCalculator` already understands these policies, but only to
 * advance an SLA clock across working hours — it has no "are we open at this
 * instant" entry point, and its interval logic is private. Channels need
 * exactly that instant check to decide whether to show a live-chat experience
 * or an offline one, so it lives here rather than being bolted onto the SLA
 * calculator, whose job is unrelated.
 *
 * A workspace with no policy configured is treated as always open — that is
 * the current behaviour everywhere else ("Always available" in the channel
 * form), and quietly switching it to closed would take working widgets
 * offline the moment this shipped.
 */
class BusinessAvailabilityService
{
    public function isOpen(?BusinessHourPolicy $policy, ?CarbonImmutable $moment = null): bool
    {
        if (! $policy) {
            return true;
        }

        $now = ($moment ?? CarbonImmutable::now())->setTimezone($policy->timezone ?: config('app.timezone'));

        if ($this->isHoliday($now)) {
            return false;
        }

        $policy->loadMissing('intervals');

        foreach ($policy->intervals->where('day_of_week', $now->dayOfWeek) as $interval) {
            /** @var BusinessHourInterval $interval */
            $start = $now->startOfDay()->setTimeFromTimeString($interval->starts_at);
            $end = $now->startOfDay()->setTimeFromTimeString($interval->ends_at);

            if ($now->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The next moment the workspace opens, so a channel can tell a customer
     * when to expect a human instead of only that nobody is there.
     */
    public function nextOpensAt(?BusinessHourPolicy $policy, ?CarbonImmutable $moment = null): ?CarbonImmutable
    {
        if (! $policy) {
            return null;
        }

        $policy->loadMissing('intervals');

        if ($policy->intervals->isEmpty()) {
            return null;
        }

        $cursor = ($moment ?? CarbonImmutable::now())->setTimezone($policy->timezone ?: config('app.timezone'));

        // Bounded at 14 days: a policy with intervals that never match (or a
        // permanent holiday run) must terminate rather than spin.
        for ($day = 0; $day < 14; $day++) {
            $date = $cursor->addDays($day);

            if ($this->isHoliday($date)) {
                continue;
            }

            foreach ($policy->intervals->where('day_of_week', $date->dayOfWeek) as $interval) {
                /** @var BusinessHourInterval $interval */
                $start = $date->startOfDay()->setTimeFromTimeString($interval->starts_at);

                if ($start->greaterThan($cursor)) {
                    return $start;
                }
            }
        }

        return null;
    }

    private function isHoliday(CarbonImmutable $moment): bool
    {
        return Holiday::query()
            ->where('is_active', true)
            ->whereDate('date', $moment->toDateString())
            ->exists();
    }
}
