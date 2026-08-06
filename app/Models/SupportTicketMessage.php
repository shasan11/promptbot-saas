<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasUuid;

    protected $fillable = [
        'support_ticket_id',
        'central_user_id',
        'body',
        'is_internal',
        'attachment_path',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function centralUser(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class);
    }
}
