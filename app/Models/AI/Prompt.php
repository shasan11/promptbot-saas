<?php

namespace App\Models\AI;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_prompts';
    protected $fillable = ['name', 'key', 'type', 'description', 'status', 'template', 'variables', 'draft_version', 'active_version_id', 'created_by', 'updated_by'];
    protected function casts(): array { return ['variables' => 'array']; }
    public function versions(): HasMany { return $this->hasMany(PromptVersion::class)->orderByDesc('version'); }
    public function activeVersion(): BelongsTo { return $this->belongsTo(PromptVersion::class, 'active_version_id'); }
}
