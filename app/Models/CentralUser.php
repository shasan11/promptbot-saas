<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Database\Factories\CentralUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class CentralUser extends Authenticatable
{
    /** @use HasFactory<CentralUserFactory> */
    use HasFactory, HasRoles, HasUuid, Notifiable;

    protected string $guard_name = 'central';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar_path',
        'email_verified_at',
        'password',
        'role',
        'department',
        'is_active',
        'suspended_at',
        'last_login_at',
        'last_login_ip',
        'locked_until',
        'password_expires_at',
        'two_factor_required',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
        'two_factor_recovery_codes_regenerated_at',
        'invitation_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_expires_at' => 'datetime',
            'two_factor_required' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_recovery_codes_regenerated_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function mustCompleteTwoFactorChallenge(): bool
    {
        return (bool) $this->two_factor_confirmed_at;
    }

    public function isPlatformOwner(): bool
    {
        return $this->hasRole('Platform Owner');
    }

    public function getDefaultGuardName(): string
    {
        return 'central';
    }
}
