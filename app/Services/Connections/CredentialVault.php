<?php

namespace App\Services\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\CredentialStatus;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionCredential;
use App\Models\Connections\CredentialRotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CredentialVault
{
    public function __construct(private readonly ConnectionAuditService $audit) {}

    public function store(Connection $connection, AuthenticationType $type, array $payload, ?User $actor = null, array $metadata = []): ConnectionCredential
    {
        $credential = $connection->credentials()->create([
            'tenant_id' => tenant('id'),
            'type' => $type,
            'status' => CredentialStatus::Active,
            'encrypted_payload' => $payload,
            'masked_secret' => $this->mask($this->primarySecret($payload)),
            'metadata' => $metadata,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $connection->forceFill(['credential_status' => CredentialStatus::Active])->save();

        return $credential;
    }

    public function rotate(ConnectionCredential $credential, array $payload, ?User $actor = null, ?AuthenticationType $type = null, ?string $reason = null, array $metadata = []): ConnectionCredential
    {
        return DB::transaction(function () use ($credential, $payload, $actor, $type, $reason, $metadata): ConnectionCredential {
            $credential->loadMissing('connection');
            $connection = $credential->connection;
            $rotation = CredentialRotation::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'connection_credential_id' => $credential->id,
                'status' => 'running',
                'reason' => $reason,
                'started_at' => now(),
                'rotated_by' => $actor?->id,
                'metadata' => $this->safeMetadata($metadata),
            ]);

            $credential->forceFill([
                'status' => CredentialStatus::Rotated,
                'rotated_at' => now(),
                'updated_by' => $actor?->id,
            ])->save();

            $newCredential = $connection->credentials()->create([
                'tenant_id' => tenant('id'),
                'type' => $type ?? $credential->type,
                'status' => CredentialStatus::Active,
                'encrypted_payload' => $payload,
                'masked_secret' => $this->mask($this->primarySecret($payload)),
                'metadata' => [
                    ...$this->safeMetadata($metadata),
                    'rotation_id' => $rotation->uuid,
                    'previous_credential_id' => $credential->id,
                ],
                'expires_at' => $metadata['expires_at'] ?? null,
                'refresh_expires_at' => $metadata['refresh_expires_at'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $connection->forceFill([
                'credential_status' => CredentialStatus::Active,
                'status' => $connection->status === ConnectionStatus::AuthenticationRequired ? ConnectionStatus::Active : $connection->status,
                'health_status' => $connection->health_status === ConnectionHealth::AuthenticationExpired ? ConnectionHealth::Unknown : $connection->health_status,
                'updated_by' => $actor?->id,
            ])->save();

            $rotation->forceFill([
                'connection_credential_id' => $newCredential->id,
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => [
                    ...($rotation->metadata ?? []),
                    'previous_credential_id' => $credential->id,
                    'new_credential_id' => $newCredential->id,
                    'new_masked_secret' => $newCredential->masked_secret,
                ],
            ])->save();

            $this->audit->record('credential.rotated', $connection, $actor, message: 'Credential rotated.', context: [
                'credential_id' => $newCredential->id,
                'previous_credential_id' => $credential->id,
                'type' => $newCredential->type->value,
                'masked_secret' => $newCredential->masked_secret,
                'reason' => $reason,
            ]);

            return $newCredential;
        });
    }

    public function revoke(ConnectionCredential $credential, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($credential, $actor, $reason): void {
            $credential->loadMissing('connection');
            $connection = $credential->connection;

            $credential->forceFill([
                'status' => CredentialStatus::Revoked,
                'updated_by' => $actor?->id,
            ])->save();

            CredentialRotation::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'connection_credential_id' => $credential->id,
                'status' => 'revoked',
                'reason' => $reason,
                'started_at' => now(),
                'completed_at' => now(),
                'rotated_by' => $actor?->id,
                'metadata' => ['revoked_credential_id' => $credential->id],
            ]);

            if (! $connection->credentials()->where('status', CredentialStatus::Active->value)->exists()) {
                $connection->forceFill([
                    'credential_status' => CredentialStatus::Revoked,
                    'status' => ConnectionStatus::AuthenticationRequired,
                    'health_status' => ConnectionHealth::AuthenticationExpired,
                    'updated_by' => $actor?->id,
                ])->save();
            }

            $this->audit->record('credential.revoked', $connection, $actor, message: 'Credential revoked.', context: [
                'credential_id' => $credential->id,
                'type' => $credential->type->value,
                'reason' => $reason,
            ], level: 'warning');
        });
    }

    public function mask(?string $secret): ?string
    {
        if (! $secret) {
            return null;
        }

        $prefix = Str::substr($secret, 0, min(7, max(2, (int) floor(Str::length($secret) / 4))));
        $suffix = Str::substr($secret, -4);

        return $prefix.'••••••••'.$suffix;
    }

    private function primarySecret(array $payload): ?string
    {
        foreach (['api_key', 'access_token', 'refresh_token', 'bearer_token', 'password', 'client_secret', 'private_key'] as $key) {
            if (! empty($payload[$key]) && is_string($payload[$key])) {
                return $payload[$key];
            }
        }

        return null;
    }

    private function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->except(['credential', 'encrypted_payload', 'api_key', 'access_token', 'refresh_token', 'bearer_token', 'password', 'client_secret', 'private_key'])
            ->all();
    }
}
