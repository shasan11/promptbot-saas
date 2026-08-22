<?php

namespace App\Models\Inbox;

use App\Models\Customer\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationRating extends Model
{
    protected $fillable = ['conversation_id', 'contact_id', 'score', 'comment', 'rated_at'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'rated_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * CSAT convention: 4 and 5 count as satisfied. Defined here rather than
     * inline at each call site so the analytics number and any UI badge can
     * never drift apart on what "satisfied" means.
     */
    public function isPositive(): bool
    {
        return $this->score >= 4;
    }
}
