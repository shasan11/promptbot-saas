<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PortalSocialAccount extends Model
{
    use CentralConnection, HasUuid;

    protected $fillable = ['portal_user_id', 'provider', 'provider_user_id', 'provider_email', 'avatar_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }
}
