<?php

namespace App\Models;

use App\Enums\PortalUserStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Notifications\Portal\ResetPasswordNotification;
use App\Notifications\Portal\VerifyEmailNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PortalUser extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasPublicUuid, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'public_uuid', 'name', 'email', 'phone', 'avatar_path', 'email_verified_at',
        'password', 'status', 'last_login_at', 'last_login_ip', 'two_factor_enabled',
        'two_factor_secret', 'two_factor_recovery_codes', 'timezone', 'locale',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'status' => PortalUserStatus::class,
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(CustomerAccount::class, 'customer_account_users')
            ->withPivot([
                'role', 'can_manage_services', 'can_manage_billing', 'can_manage_members',
                'can_manage_support', 'service_access', 'invited_by', 'joined_at',
            ])->withTimestamps();
    }

    public function belongsToAccount(CustomerAccount|int $account): bool
    {
        $id = $account instanceof CustomerAccount ? $account->getKey() : $account;

        return $this->accounts()->whereKey($id)->exists();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(PortalSocialAccount::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
