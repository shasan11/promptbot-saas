<?php

namespace App\Models\Knowledge;

use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeCollection extends Model
{
    use HasPublicUuid, SoftDeletes;

    /** Product decision: deep trees are unnavigable and make ACL inheritance opaque. */
    public const MAX_DEPTH = 4;

    protected $fillable = [
        'knowledge_base_id', 'parent_id', 'name', 'slug', 'description',
        'icon', 'depth', 'sort_order', 'status', 'created_by',
    ];

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class, 'knowledge_collection_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class, 'knowledge_collection_id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(KnowledgeFaq::class, 'knowledge_collection_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * IDs of this collection and every descendant. Access granted on a parent
     * collection cascades down, so permission resolution needs the whole
     * subtree — computed from a single flat fetch rather than N recursive
     * queries, since a base rarely holds more than a few hundred collections.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $all = static::query()
            ->where('knowledge_base_id', $this->knowledge_base_id)
            ->get(['id', 'parent_id']);

        $byParent = $all->groupBy('parent_id');
        $ids = [$this->id];
        $queue = [$this->id];

        while ($queue) {
            $current = array_shift($queue);

            foreach ($byParent->get($current, collect()) as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
