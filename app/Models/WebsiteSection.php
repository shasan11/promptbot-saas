<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSection extends Model
{
    use HasUuid;

    public const TYPES = [
        'hero', 'logo_cloud', 'feature_grid', 'feature_list', 'feature_showcase', 'image_text',
        'stats', 'testimonials', 'pricing', 'comparison_table', 'integrations', 'how_it_works',
        'faq', 'cta', 'newsletter', 'contact_form', 'video', 'gallery', 'rich_text', 'spacer', 'custom_html',
    ];

    protected $fillable = [
        'website_page_id',
        'type',
        'sort_order',
        'content',
        'is_hidden',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'content' => 'array',
        'is_hidden' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }
}
