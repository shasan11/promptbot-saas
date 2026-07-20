<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicUuid
{
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_uuid)) {
                $model->public_uuid = (string) Str::uuid();
            }
        });

        static::updating(function ($model): void {
            if ($model->isDirty('public_uuid')) {
                $model->public_uuid = $model->getOriginal('public_uuid');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }
}
