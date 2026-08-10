<?php

namespace App\Models\AI;

use App\Enums\AI\ProviderStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderConfig extends Model
{
    use HasPublicUuid;

    protected $table = 'ai_provider_configs';

    protected $fillable = [
        'name', 'provider', 'enabled', 'status', 'default_chat_model', 'default_fast_model',
        'default_reasoning_model', 'default_embedding_model', 'base_url', 'organization',
        'credentials_encrypted', 'configuration', 'capabilities', 'last_tested_at',
        'last_successful_test_at', 'last_test_status', 'last_error_code', 'last_error_message',
        'consecutive_failures', 'circuit_open_until', 'created_by', 'updated_by',
    ];

    protected $hidden = ['credentials_encrypted'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'status' => ProviderStatus::class,
            'credentials_encrypted' => 'encrypted:array',
            'configuration' => 'array',
            'capabilities' => 'array',
            'last_tested_at' => 'datetime',
            'last_successful_test_at' => 'datetime',
            'circuit_open_until' => 'datetime',
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'provider_config_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasCredential(): bool
    {
        return filled($this->credentials_encrypted['api_key'] ?? null);
    }

    public function safePayload(): array
    {
        return [
            'public_uuid' => $this->public_uuid,
            'name' => $this->name,
            'provider' => $this->provider,
            'enabled' => $this->enabled,
            'status' => $this->status->value,
            'default_chat_model' => $this->default_chat_model,
            'default_fast_model' => $this->default_fast_model,
            'default_reasoning_model' => $this->default_reasoning_model,
            'default_embedding_model' => $this->default_embedding_model,
            'base_url' => $this->base_url,
            'organization' => $this->organization,
            'configuration' => $this->configuration ?? [],
            'capabilities' => $this->capabilities ?? [],
            'credential_configured' => $this->hasCredential() || $this->provider === 'ollama',
            'last_tested_at' => $this->last_tested_at,
            'last_successful_test_at' => $this->last_successful_test_at,
            'last_test_status' => $this->last_test_status,
            'last_error_code' => $this->last_error_code,
            'last_error_message' => $this->last_error_message,
            'circuit_open_until' => $this->circuit_open_until,
        ];
    }
}
