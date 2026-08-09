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
        $scopes = $this->validateRequestedScopes($integration, $scopes, $connection?->usage ?? []);

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
            'scopes' => $scopes,
            'redirect_path' => $this->safeRedirectPath($redirectPath),
            'metadata' => [
                'provider' => $integration->provider,
                'integration_key' => $integration->key,
                'scope_descriptions' => $this->scopeDescriptions($integration, $scopes),
                'requires_reauthorization_on_scope_change' => true,
            ],
            'authorized_by' => $actor->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'expires_at' => now()->addMinutes(10),
        ]);

        return [
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scopes' => $scopes,
            'scope_descriptions' => $this->scopeDescriptions($integration, $scopes),
            'redirect_path' => $this->safeRedirectPath($redirectPath),
        ];
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

        if ($expectedScopes && array_diff($this->normaliseScopes($expectedScopes), $record->scopes ?? [])) {
            throw new InvalidArgumentException('OAuth authorization scope validation failed.');
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return $record;
    }

    private function safeRedirectPath(string $path): string
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/^https?:/i', $path)) {
            throw new InvalidArgumentException('OAuth redirect path is not allowed.');
        }

        return $path;
    }

    public function validateRequestedScopes(ConnectionIntegration $integration, array $requestedScopes = [], array $usage = []): array
    {
        $policy = $integration->credential_schema['oauth'] ?? [];
        $allowed = $this->normaliseScopes($policy['allowed_scopes'] ?? []);
        $defaults = $this->normaliseScopes($policy['default_scopes'] ?? []);
        $usageScopes = $this->scopesForUsage($policy, $usage);
        $requested = $this->normaliseScopes($requestedScopes);

        if ($requested === []) {
            $requested = array_values(array_unique([...$defaults, ...$usageScopes]));
        }

        if ($allowed !== [] && array_diff($requested, $allowed)) {
            throw new InvalidArgumentException('OAuth requested scopes exceed this integration scope policy.');
        }

        $missing = array_diff($usageScopes, $requested);

        if ($missing) {
            throw new InvalidArgumentException('OAuth requested scopes do not satisfy the selected connection usage.');
        }

        return $requested;
    }

    public function scopePolicy(ConnectionIntegration $integration, array $usage = []): array
    {
        $policy = $integration->credential_schema['oauth'] ?? [];
        $defaultScopes = $this->normaliseScopes($policy['default_scopes'] ?? []);
        $usageScopes = $this->scopesForUsage($policy, $usage);
        $recommended = array_values(array_unique([...$defaultScopes, ...$usageScopes]));

        return [
            'allowed_scopes' => $this->normaliseScopes($policy['allowed_scopes'] ?? []),
            'default_scopes' => $defaultScopes,
            'required_scopes' => $usageScopes,
            'recommended_scopes' => $recommended,
            'scope_descriptions' => $this->scopeDescriptions($integration, $recommended),
            'requires_reauthorization_on_scope_change' => true,
        ];
    }

    private function scopesForUsage(array $policy, array $usage): array
    {
        $byUsage = $policy['required_scopes_by_usage'] ?? [];
        $scopes = [];

        foreach ($usage as $usageKey) {
            $scopes = [...$scopes, ...($byUsage[$usageKey] ?? [])];
        }

        return $this->normaliseScopes($scopes);
    }

    private function scopeDescriptions(ConnectionIntegration $integration, array $scopes): array
    {
        $descriptions = $integration->credential_schema['oauth']['scope_descriptions'] ?? [];

        return collect($scopes)
            ->mapWithKeys(fn (string $scope): array => [$scope => $descriptions[$scope] ?? $scope])
            ->all();
    }

    private function normaliseScopes(array $scopes): array
    {
        return collect($scopes)
            ->map(fn ($scope) => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
