<?php

namespace App\Models\Knowledge;

use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocumentVersion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'knowledge_document_id', 'version_number', 'previous_version_id', 'title',
        'original_filename', 'storage_disk', 'storage_path', 'file_size', 'checksum',
        'extracted_text', 'content_hash', 'character_count', 'change_summary',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected $hidden = ['extracted_text'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
