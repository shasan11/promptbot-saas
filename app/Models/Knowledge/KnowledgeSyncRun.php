<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\SyncStatus;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSyncRun extends Model
{
    use HasPublicUuid;

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_API = 'api';

    protected $fillable = [
        'knowledge_source_id', 'trigger', 'status', 'started_at', 'completed_at',
        'duration_ms', 'items_discovered', 'items_created', 'items_updated',
        'items_unchanged', 'items_deleted', 'items_skipped', 'items_failed',
        'last_error', 'summary', 'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    // Named for the column, not the relation, to avoid shadowing the `trigger`
    // attribute — Eloquent resolves `$run->trigger` to the relation otherwise.
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
