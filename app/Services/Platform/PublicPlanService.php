<?php

namespace App\Services\Platform;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Builder;

class PublicPlanService
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function query(): Builder
    {
        $query = Plan::query()->where('is_active', true)->where('is_public', true);
        $allowed = $this->allowedIds();

        return $allowed === [] ? $query : $query->whereIn('id', $allowed);
    }

    public function allows(Plan|int|string $plan): bool
    {
        $id = $plan instanceof Plan ? $plan->getKey() : $plan;

        return $this->query()->whereKey($id)->exists();
    }

    private function allowedIds(): array
    {
        $raw = (string) $this->settings->get('registration', 'allowed_plan_ids', '');

        return collect(preg_split('/\s*,\s*/', trim($raw), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($id) => ctype_digit((string) $id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
