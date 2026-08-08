<?php

namespace App\Models\Knowledge\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a UUID on create and makes it the route key.
 *
 * Every knowledge resource is addressed publicly by UUID rather than by its
 * auto-increment id. Two reasons: a sequential id in a URL discloses how much
 * knowledge a workspace holds, and — more importantly — it makes ID-guessing
 * attempts against another workspace's records obviously invalid rather than
 * plausible. (Isolation itself comes from the per-tenant database, not from
 * the identifier; this is defence in depth.)
 */
trait HasPublicUuid
{
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
