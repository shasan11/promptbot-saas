<?php

namespace App\Models;

use App\Enums\AI\AIPurpose;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelAssignment extends Model
{
    use HasUuid;
    use UsesCentralConnection;

    protected $fillable = [
        'purpose',
        'ai_model_id',
        'priority',
        'is_enabled',
    ];

    protected $casts = [
        'purpose' => AIPurpose::class,
        'priority' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
