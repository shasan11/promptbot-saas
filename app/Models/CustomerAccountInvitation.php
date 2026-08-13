<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CustomerAccountInvitation extends Model
{
    use CentralConnection, HasUuid;

    protected $guarded = [];
    protected $hidden = ['token_hash'];
    protected $casts = [
        'expires_at' => 'datetime', 'accepted_at' => 'datetime',
        'can_manage_services' => 'boolean', 'can_manage_billing' => 'boolean',
        'can_manage_members' => 'boolean', 'can_manage_support' => 'boolean',
        'tenant_ids' => 'array',
    ];

    public function account(): BelongsTo { return $this->belongsTo(CustomerAccount::class, 'customer_account_id'); }
    public function inviter(): BelongsTo { return $this->belongsTo(PortalUser::class, 'invited_by'); }
}
