<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsitePage extends Model
{
    use HasUuid;

    protected $fillable = [
        'title',
        'slug',
        'status',
        'seo',
        'published_at',
    ];

    protected $casts = [
        'seo' => 'array',
        'published_at' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(WebsiteSection::class)->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
