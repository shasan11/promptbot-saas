<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
    use HasPublicUuid;

    protected $casts = [
        'data' => 'array',
        'status' => TenantStatus::class,
        'provisioned_at' => 'datetime',
        'suspended_at' => 'datetime',
        'deleted_at' => 'datetime',
        'database_created_by_app' => 'boolean',
        'tenancy_db_password' => 'encrypted',
    ];

    protected $hidden = [
        'tenancy_db_password',
        'data',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(TenantFeatureOverride::class);
    }

    public function provisioningLogs(): HasMany
    {
        return $this->hasMany(ProvisioningLog::class);
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

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'public_uuid',
            'company_name',
            'slug',
            'status',
            'plan_id',
            'provisioning_step',
            'last_provisioning_error',
            'provisioned_at',
            'suspended_at',
            'deleted_at',
            'tenancy_db_connection',
            'tenancy_db_name',
            'tenancy_db_host',
            'tenancy_db_port',
            'tenancy_db_username',
            'tenancy_db_password',
            'database_created_by_app',
            'created_at',
            'updated_at',
            'data',
        ];
    }
}
