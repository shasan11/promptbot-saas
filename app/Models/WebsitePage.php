<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebsitePage extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'status',
        'page_type', 'template', 'canonical_url', 'robots_index', 'robots_follow',
        'open_graph', 'twitter', 'schema_json', 'scheduled_at', 'created_by', 'updated_by',
        'seo',
        'published_at',
    ];

    protected $casts = [
        'seo' => 'array',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'robots_index' => 'boolean', 'robots_follow' => 'boolean',
        'open_graph' => 'array', 'twitter' => 'array', 'schema_json' => 'array',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(WebsiteSection::class)->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsitePageRevision::class)->latest('version');
    }
}
