<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_amount',
        'total',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
