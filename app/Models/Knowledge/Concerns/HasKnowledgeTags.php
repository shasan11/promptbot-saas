<?php

namespace App\Models\Knowledge\Concerns;

use App\Models\Knowledge\KnowledgeTag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasKnowledgeTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(KnowledgeTag::class, 'taggable', 'knowledge_taggables', 'taggable_id', 'knowledge_tag_id')
            ->withTimestamps();
    }

    /**
     * Replaces this record's tags with the given names, creating any that do not
     * exist yet and keeping KnowledgeTag::usage_count accurate on both sides of
     * the change.
     *
     * @param  array<int, string>  $names
     */
    public function syncTagNames(array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => KnowledgeTag::findOrCreateByName($name)->id)
            ->all();

        $changes = $this->tags()->sync($ids);

        KnowledgeTag::recountUsage(array_merge($changes['attached'] ?? [], $changes['detached'] ?? []));
    }
}
