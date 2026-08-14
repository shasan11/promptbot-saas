<?php

namespace App\Models;

use App\Enums\AI\AIModelCapability;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    use HasUuid;
    use UsesCentralConnection;

    protected $fillable = [
        'ai_provider_id',
        'model_key',
        'display_name',
        'capability',
        'context_window',
        'max_output_tokens',
        'embedding_dimensions',
        'input_cost_per_million_tokens',
        'output_cost_per_million_tokens',
        'supports_streaming',
        'supports_json_mode',
        'is_enabled',
        'is_default_for_capability',
        'metadata',
    ];

    protected $casts = [
        'capability' => AIModelCapability::class,
        'context_window' => 'integer',
        'max_output_tokens' => 'integer',
        'embedding_dimensions' => 'integer',
        'input_cost_per_million_tokens' => 'decimal:6',
        'output_cost_per_million_tokens' => 'decimal:6',
        'supports_streaming' => 'boolean',
        'supports_json_mode' => 'boolean',
        'is_enabled' => 'boolean',
        'is_default_for_capability' => 'boolean',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AiModelAssignment::class);
    }
}
