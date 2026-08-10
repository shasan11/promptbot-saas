<?php

namespace App\Models\AI;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptVersion extends Model
{
    use HasPublicUuid;
    public const UPDATED_AT = null;
    protected $table = 'ai_prompt_versions';
    protected $fillable = ['prompt_id', 'version', 'template', 'configuration', 'created_by'];
    protected function casts(): array { return ['configuration' => 'array']; }
    public function prompt(): BelongsTo { return $this->belongsTo(Prompt::class); }
}
