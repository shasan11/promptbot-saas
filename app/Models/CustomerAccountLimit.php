<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CustomerAccountLimit extends Model
{
    use CentralConnection;

    protected $fillable = ['customer_account_id', 'feature_key', 'scope', 'limit_value', 'unit', 'period', 'is_enforced', 'metadata'];

    protected function casts(): array
    {
        return ['limit_value' => 'decimal:2', 'is_enforced' => 'boolean', 'metadata' => 'array'];
    }

    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class);
    }
}
