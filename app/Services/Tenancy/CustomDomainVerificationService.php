<?php

namespace App\Services\Tenancy;

use App\Models\Domain;
use Illuminate\Support\Str;

class CustomDomainVerificationService
{
    public function issueToken(Domain $domain): string
    {
        $token = 'promptbot-'.Str::random(32);
        $domain->forceFill([
            'verification_token' => $token,
            'verification_status' => 'pending',
            'last_verification_error' => null,
        ])->save();

        return $token;
    }

    public function verify(Domain $domain): bool
    {
        $records = dns_get_record('_promptbot.'.$domain->domain, DNS_TXT) ?: [];
        $found = collect($records)->contains(fn (array $record) => ($record['txt'] ?? null) === $domain->verification_token);

        $domain->forceFill([
            'verification_status' => $found ? 'verified' : 'failed',
            'verified_at' => $found ? now() : null,
            'last_verification_error' => $found ? null : 'Expected TXT record was not found.',
        ])->save();

        return $found;
    }
}
