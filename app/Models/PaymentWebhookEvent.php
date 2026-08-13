<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasUuid;

    protected $guarded = [];
    protected $hidden = ['payload_hash', 'failure_reason'];
    protected $casts = ['processed_at' => 'datetime'];
}
