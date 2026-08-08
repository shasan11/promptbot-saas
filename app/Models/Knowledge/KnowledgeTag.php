<?php

namespace App\Models\Knowledge;

use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeTag extends Model
{
    use HasPublicUuid;

    protected $fillable = ['name', 'slug', 'description', 'color', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function findOrCreateByName(string $name): self
    {
        $slug = Str::slug($name);

        return static::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'created_by' => auth('tenant')->id()]
        );
    }

    /**
     * Recomputes usage_count from the pivot for the given tags. Called after
     * sync rather than incremented per attach so the counter self-heals if a
     * taggable row is removed by a cascade delete.
     *
     * @param  array<int, int>  $tagIds
     */
    public static function recountUsage(array $tagIds): void
    {
        if (! $tagIds = array_unique(array_filter($tagIds))) {
            return;
        }

        $counts = DB::table('knowledge_taggables')
            ->select('knowledge_tag_id', DB::raw('count(*) as aggregate'))
            ->whereIn('knowledge_tag_id', $tagIds)
            ->groupBy('knowledge_tag_id')
            ->pluck('aggregate', 'knowledge_tag_id');

        foreach ($tagIds as $id) {
            static::query()->whereKey($id)->update(['usage_count' => (int) ($counts[$id] ?? 0)]);
        }
    }
}
