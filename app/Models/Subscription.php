<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Subscription extends Model
{
    use CentralConnection, HasFactory, HasPublicUuid;

    protected $fillable = [
        'tenant_id',
        'public_uuid',
        'plan_id',
        'status',
        'billing_interval',
        'starts_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'cancelled_at',
        'grace_ends_at',
        'external_provider',
        'external_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'plan_id' => 'integer',
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
