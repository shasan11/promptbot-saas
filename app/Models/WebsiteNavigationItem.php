<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteNavigationItem extends Model
{
    use HasUuid;

    protected $fillable = ['label', 'url', 'menu_group', 'type', 'website_page_id', 'parent_id', 'sort_order', 'is_active', 'open_new_tab', 'style'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'open_new_tab' => 'boolean',
    ];

    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
}
