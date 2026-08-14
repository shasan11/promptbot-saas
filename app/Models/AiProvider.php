<?php

namespace App\Models;

use App\Enums\AI\AIProviderDriver;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiProvider extends Model
{
    use HasUuid;
    use UsesCentralConnection;

    protected $fillable = [
        'driver',
        'name',
        'slug',
        'base_url',
        'api_key',
        'organization_id',
        'extra_headers',
        'is_enabled',
        'is_default',
        'priority',
        'timeout_seconds',
        'max_retries',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
        'last_success_at',
        'metadata',
    ];

    protected $casts = [
        'driver' => AIProviderDriver::class,
        'api_key' => 'encrypted',
        'organization_id' => 'encrypted',
        'extra_headers' => 'encrypted:array',
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'priority' => 'integer',
        'timeout_seconds' => 'integer',
        'max_retries' => 'integer',
        'last_tested_at' => 'datetime',
        'last_success_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** Never allow this model's array/JSON form to leak the secret columns. */
    protected $hidden = [
        'api_key',
        'organization_id',
        'extra_headers',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    public function maskedKey(): ?string
    {
        return $this->api_key ? Str::mask($this->api_key, '•', 0, -4) : null;
    }

    public function hasKey(): bool
    {
        return filled($this->api_key);
    }
}
