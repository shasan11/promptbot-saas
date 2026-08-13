<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasUuid;

    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'metadata' => 'array'];
    protected $hidden = ['provider_reference', 'metadata'];

    public function account(): BelongsTo { return $this->belongsTo(CustomerAccount::class, 'customer_account_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
