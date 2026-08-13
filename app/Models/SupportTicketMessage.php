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
        'portal_user_id',
        'body',
        'is_internal',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'attachment_size' => 'integer',
    ];

    protected $hidden = ['attachment_path'];
    protected $appends = ['has_attachment'];

    public function getHasAttachmentAttribute(): bool { return filled($this->attachment_path); }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function centralUser(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class);
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }
}
