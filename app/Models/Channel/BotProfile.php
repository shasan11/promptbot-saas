<?php

namespace App\Models\Channel;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How a bot behaves — independent of which channel it is answering on.
 *
 * A channel with no profile attached uses the defaults declared here, which
 * are the values that were previously hardcoded in the reply orchestrator.
 * That keeps every existing channel behaving exactly as it did before this
 * table existed, and makes attaching a profile a purely additive act.
 */
class BotProfile extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'name', 'tone', 'response_length', 'language_policy', 'default_language',
        'escalate_on_request', 'escalate_after_failures', 'escalate_on_negative_sentiment',
        'escalate_on_risk_flags', 'escalation_team_id', 'is_default', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'escalate_on_request' => 'boolean',
            'escalate_on_negative_sentiment' => 'boolean',
            'escalate_on_risk_flags' => 'boolean',
            'is_default' => 'boolean',
            'escalate_after_failures' => 'integer',
        ];
    }

    /**
     * The behaviour a channel without an attached profile gets. Mirrors the
     * column defaults so "no profile" and "a freshly created profile" behave
     * identically — a difference between those two would be invisible in the
     * UI and very confusing to debug.
     */
    public static function defaults(): self
    {
        return new self([
            'name' => 'Default',
            'tone' => 'professional',
            'response_length' => 'balanced',
            'language_policy' => 'match_customer',
            'default_language' => 'en',
            'escalate_on_request' => true,
            'escalate_after_failures' => 2,
            'escalate_on_negative_sentiment' => true,
            'escalate_on_risk_flags' => true,
        ]);
    }

    /**
     * The profile a channel with none attached should use, if the workspace
     * nominated one.
     *
     * Deliberately not memoised in a static: this runs inside queue workers
     * that switch tenants between jobs, and a cached profile would be answered
     * from the wrong tenant's database. The table holds a handful of rows, so
     * the query is not worth that risk.
     */
    public static function workspaceDefault(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function escalationTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'escalation_team_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Style guidance handed to the answer generator, in plain instruction form. */
    public function styleInstruction(): string
    {
        $tone = match ($this->tone) {
            'friendly' => 'Write warmly and personably, like a helpful colleague.',
            'casual' => 'Write casually and conversationally, using plain everyday language.',
            default => 'Write in a professional, courteous tone.',
        };

        $length = match ($this->response_length) {
            'short' => 'Answer in one or two sentences. Never pad.',
            'detailed' => 'Give a thorough answer, including relevant caveats and next steps.',
            default => 'Keep the answer brief but complete — usually two to four sentences.',
        };

        return $tone.' '.$length;
    }
}
