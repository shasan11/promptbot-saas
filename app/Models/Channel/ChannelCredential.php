<?php

namespace App\Models\Channel;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelCredential extends Model
{
    protected $fillable = ['channel_id', 'provider', 'encrypted_payload', 'status', 'last_rotated_at', 'rotated_by'];
    protected $hidden = ['encrypted_payload'];
    protected function casts(): array { return ['encrypted_payload' => 'encrypted:array', 'last_rotated_at' => 'datetime']; }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function rotatedBy(): BelongsTo { return $this->belongsTo(User::class, 'rotated_by'); }
}
