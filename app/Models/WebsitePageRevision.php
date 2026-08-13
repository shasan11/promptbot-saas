<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePageRevision extends Model
{
    use HasUuid;

    protected $table = 'website_page_versions';
    protected $fillable = ['website_page_id', 'version', 'content', 'created_by'];
    protected $casts = ['content' => 'array', 'version' => 'integer'];

    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }
}
