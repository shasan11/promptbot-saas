<?php

namespace App\Models\Connections\Concerns;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model): void {
            if (! $model->tenant_id && function_exists('tenant') && tenancy()->initialized) {
                $model->tenant_id = tenant('id');
            }
        });
    }
}
