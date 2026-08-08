<?php

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeFaqVersion extends Model
{
    protected $fillable = [
        'knowledge_faq_id', 'version_number', 'question', 'answer',
        'change_summary', 'created_by',
    ];

    public function faq(): BelongsTo
    {
        return $this->belongsTo(KnowledgeFaq::class, 'knowledge_faq_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
