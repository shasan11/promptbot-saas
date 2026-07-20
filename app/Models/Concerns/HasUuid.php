<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuid
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            $keyName = $model->getKeyName();

            if (empty($model->{$keyName})) {
                $model->{$keyName} = (string) Str::uuid();
            }
        });

        static::updating(function ($model): void {
            $keyName = $model->getKeyName();

            if ($model->isDirty($keyName)) {
                $model->{$keyName} = $model->getOriginal($keyName);
            }
        });
    }
}
