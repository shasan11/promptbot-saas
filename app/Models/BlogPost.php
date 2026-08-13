<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'featured_image', 'status', 'seo', 'published_at', 'scheduled_at', 'canonical_url', 'robots_index', 'author_id'];
    protected $casts = ['seo' => 'array', 'published_at' => 'datetime', 'scheduled_at' => 'datetime', 'robots_index' => 'boolean'];

    public function author(): BelongsTo { return $this->belongsTo(CentralUser::class, 'author_id'); }
    public function categories(): BelongsToMany { return $this->belongsToMany(WebsiteCategory::class, 'website_post_categories'); }
    public function tags(): BelongsToMany { return $this->belongsToMany(WebsiteTag::class, 'website_post_tags'); }
}
