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
        'customer_account_id',
        'public_uuid',
        'plan_id',
        'pending_plan_id',
        'coupon_id',
        'status',
        'billing_interval',
        'pending_billing_interval',
        'pending_change_effective_at',
        'starts_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'cancelled_at',
        'cancel_at',
        'cancellation_reason',
        'cancellation_feedback',
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
            'cancel_at' => 'datetime',
            'pending_change_effective_at' => 'datetime',
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

    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->latest('effective_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
