<?php

namespace App\Models;

use App\Enums\CustomerAccountStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CustomerAccount extends Model
{
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    public const DEFAULT_ACCOUNT_NUMBER = 'ACC-DEFAULT';

    protected static function booted(): void
    {
        static::deleting(function (CustomerAccount $account): void {
            if ($account->isSystemDefault()) {
                throw new \DomainException('The system Default Account cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'public_uuid', 'name', 'legal_name', 'account_number', 'status', 'type',
        'primary_owner_user_id', 'billing_email', 'billing_phone', 'country', 'state',
        'city', 'address_line_1', 'address_line_2', 'postal_code', 'tax_number',
        'vat_number', 'default_currency', 'timezone', 'locale', 'billing_mode',
        'billing_day', 'metadata', 'suspended_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerAccountStatus::class,
            'metadata' => 'array',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isSystemDefault(): bool
    {
        return $this->account_number === self::DEFAULT_ACCOUNT_NUMBER
            || (bool) data_get($this->metadata, 'system_default', false);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'primary_owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(PortalUser::class, 'customer_account_users')
            ->withPivot([
                'role', 'can_manage_services', 'can_manage_billing', 'can_manage_members',
                'can_manage_support', 'service_access', 'invited_by', 'joined_at',
            ])->withTimestamps();
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function tenantsVisibleTo(PortalUser $user): HasMany
    {
        $relation = $this->tenants();
        $membership = $this->users()->where('portal_users.id', $user->getKey())->first()?->pivot;

        if (! $membership || ($membership->role !== 'owner' && $membership->service_access === 'selected')) {
            $relation->whereIn('tenants.id', fn ($query) => $query->from('customer_account_user_tenants')
                ->select('tenant_id')->where('customer_account_id', $this->getKey())->where('portal_user_id', $user->getKey()));
        }

        return $relation;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(BillingProfile::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PortalPaymentMethod::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerAccountActivity::class)->latest();
    }

    public function limits(): HasMany
    {
        return $this->hasMany(CustomerAccountLimit::class);
    }
}
