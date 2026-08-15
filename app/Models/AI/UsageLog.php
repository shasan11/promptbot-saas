<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    protected $table = 'ai_usage_logs';
    protected $guarded = ['id'];
    protected function casts(): array { return ['occurred_at' => 'datetime', 'estimated_cost' => 'decimal:8', 'metadata' => 'array']; }
    public function run(): BelongsTo { return $this->belongsTo(Run::class, 'ai_run_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}
