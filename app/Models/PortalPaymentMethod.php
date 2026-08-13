<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PortalPaymentMethod extends Model
{
    use CentralConnection, HasUuid;

    protected $fillable = [
        'customer_account_id', 'provider', 'provider_reference', 'type', 'brand',
        'last_four', 'expires_month', 'expires_year', 'is_default', 'metadata',
    ];

    protected $hidden = ['provider_reference', 'metadata'];

    protected $casts = ['is_default' => 'boolean', 'metadata' => 'array'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
