<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CustomerAccountActivity extends Model
{
    use CentralConnection, HasUuid;

    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'is_customer_visible' => 'boolean'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
