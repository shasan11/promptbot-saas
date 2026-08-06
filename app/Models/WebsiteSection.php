<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSection extends Model
{
    use HasUuid;

    public const TYPES = ['rich_text', 'hero', 'cta'];

    protected $fillable = [
        'website_page_id',
        'type',
        'sort_order',
        'content',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'content' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }
}
