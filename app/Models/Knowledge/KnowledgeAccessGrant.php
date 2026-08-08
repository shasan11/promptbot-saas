<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\GranteeType;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeAccessGrant extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'knowledge_base_id', 'knowledge_collection_id', 'grantee_type', 'grantee_id',
        'grantee_key', 'grantee_label', 'access_level', 'created_by', 'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'grantee_type' => GranteeType::class,
            'access_level' => AccessLevel::class,
        ];
    }

    protected static function booted(): void
    {
        // The dedupe_key is the real unique constraint (see the migration —
        // three of the tuple's columns are nullable and MySQL would otherwise
        // let duplicates through), so it must never be set by a caller.
        static::saving(function (self $grant): void {
            $grant->dedupe_key = $grant->computeDedupeKey();
        });
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCollection::class, 'knowledge_collection_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function computeDedupeKey(): string
    {
        return hash('sha256', implode('|', [
            $this->knowledge_base_id,
            $this->knowledge_collection_id ?? '-',
            $this->grantee_type instanceof GranteeType ? $this->grantee_type->value : $this->grantee_type,
            $this->grantee_id ?? '-',
            $this->grantee_key ?? '-',
        ]));
    }
}
