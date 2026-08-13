<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'customer_account_id',
        'number',
        'idempotency_key',
        'status',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'currency',
        'issued_on',
        'due_on',
        'voided_at',
        'paid_at',
        'metadata',
        'billing_snapshot',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_on' => 'date',
        'due_on' => 'date',
        'voided_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'billing_snapshot' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
