<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    use HasPublicUuid;

    protected $fillable = [
        'public_uuid',
        'domain',
        'tenant_id',
        'type',
        'verification_token',
        'verification_status',
        'verified_at',
        'is_primary',
        'ssl_status',
        'last_verification_error',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    protected function domain(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => strtolower(trim($value)),
        );
    }
}
