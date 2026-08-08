<?php

namespace App\Services\Connections;

use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionIntegration;
use App\Models\Connections\OAuthAuthorizationState;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OAuthStateService
{
    public function create(ConnectionIntegration $integration, ?Connection $connection, User $actor, Request $request, array $scopes, string $redirectPath = '/connections'): array
    {
        $state = Str::random(64);
        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        OAuthAuthorizationState::create([
            'tenant_id' => tenant('id'),
            'connection_integration_id' => $integration->id,
            'connection_id' => $connection?->id,
            'state_hash' => hash('sha256', $state),
            'code_verifier' => $verifier,
            'code_challenge' => $challenge,
            'scopes' => array_values(array_unique($scopes)),
            'redirect_path' => $this->safeRedirectPath($redirectPath),
            'authorized_by' => $actor->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'expires_at' => now()->addMinutes(10),
        ]);

        return ['state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'];
    }

    public function consume(string $state, array $expectedScopes = []): OAuthAuthorizationState
    {
        $record = OAuthAuthorizationState::query()
            ->where('tenant_id', tenant('id'))
            ->where('state_hash', hash('sha256', $state))
            ->whereNull('consumed_at')
            ->first();

        if (! $record || $record->expires_at->isPast()) {
            throw new InvalidArgumentException('OAuth authorization state is invalid or expired.');
        }

        if ($expectedScopes && array_diff($expectedScopes, $record->scopes ?? [])) {
            throw new InvalidArgumentException('OAuth authorization scope validation failed.');
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return $record;
    }

    private function safeRedirectPath(string $path): string
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/^https?:/i', $path)) {
            return '/connections';
        }

        return $path;
    }
}
