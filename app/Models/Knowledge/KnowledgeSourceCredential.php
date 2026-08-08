<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Secrets for connector-backed sources.
 *
 * `secret` is encrypted at rest by the `encrypted:array` cast, and the model is
 * never serialised into an API resource or Inertia payload — callers ask for
 * specific fields through the connector, they do not receive the model. It is
 * a separate table (rather than a column on knowledge_sources) so that the
 * ciphertext is not loaded, logged, or accidentally dumped by the many list
 * queries that touch a source row.
 */
class KnowledgeSourceCredential extends Model
{
    protected $fillable = [
        'knowledge_source_id', 'provider', 'external_account_label',
        'secret', 'expires_at', 'validation_status',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted:array',
            'expires_at' => 'datetime',
            'last_validated_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return array<string, mixed> Safe metadata for the UI — never the secret itself. */
    public function summary(): array
    {
        return [
            'provider' => $this->provider,
            'account' => $this->external_account_label,
            'status' => $this->validation_status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'last_validated_at' => $this->last_validated_at?->toIso8601String(),
        ];
    }
}
