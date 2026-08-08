<?php

namespace App\Models\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\ConnectionType;
use App\Enums\Connections\CredentialStatus;
use App\Enums\Connections\Environment;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Connection extends Model
{
    use BelongsToTenant, HasUuid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'connection_integration_id',
        'name',
        'description',
        'status',
        'health_status',
        'connection_type',
        'auth_type',
        'environment',
        'provider_account_name',
        'provider_account_id',
        'usage',
        'configuration',
        'credential_status',
        'created_by',
        'updated_by',
        'owner_user_id',
        'owner_team_id',
        'technical_contact',
        'business_contact',
        'connected_at',
        'last_checked_at',
        'last_successful_check_at',
        'last_error_at',
        'last_error_code',
        'last_error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'health_status' => ConnectionHealth::class,
            'connection_type' => ConnectionType::class,
            'auth_type' => AuthenticationType::class,
            'environment' => Environment::class,
            'credential_status' => CredentialStatus::class,
            'usage' => 'array',
            'configuration' => 'array',
            'connected_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_successful_check_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ConnectionIntegration::class, 'connection_integration_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ConnectionCredential::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ConnectionResource::class);
    }

    public function dataSources(): HasMany
    {
        return $this->hasMany(DataSource::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConnectionLog::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ConnectionHealthCheck::class);
    }

    public function rateLimits(): HasMany
    {
        return $this->hasMany(ProviderRateLimit::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(ConnectionAccessGrant::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ConnectionAction::class);
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(ConnectionTrigger::class);
    }

    public function apiOperations(): HasMany
    {
        return $this->hasMany(ApiOperation::class);
    }

    public function actionExecutions(): HasMany
    {
        return $this->hasMany(ConnectionActionExecution::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(ConnectionUsageRecord::class);
    }

    public function agentAccess(): HasMany
    {
        return $this->hasMany(ConnectionAgentAccess::class);
    }

    public function workflowAccess(): HasMany
    {
        return $this->hasMany(ConnectionWorkflowAccess::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function ownerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_team_id');
    }
}
