<?php

namespace App\Services\Connections;

use App\Enums\Connections\ConnectionErrorCategory;

class RetryPolicyService
{
    public function classify(?int $httpStatus = null, ?string $errorCode = null): ConnectionErrorCategory
    {
        if ($httpStatus === null && $errorCode && preg_match('/^HTTP_(\d{3})$/', $errorCode, $matches)) {
            $httpStatus = (int) $matches[1];
        }

        if ($httpStatus === 401) {
            return ConnectionErrorCategory::Authentication;
        }

        if ($httpStatus === 403) {
            return ConnectionErrorCategory::Authorization;
        }

        if ($httpStatus === 404) {
            return ConnectionErrorCategory::ResourceMissing;
        }

        if ($httpStatus === 408) {
            return ConnectionErrorCategory::Timeout;
        }

        if ($httpStatus === 429) {
            return ConnectionErrorCategory::RateLimit;
        }

        if ($httpStatus && $httpStatus >= 500) {
            return ConnectionErrorCategory::ProviderUnavailable;
        }

        return match ($errorCode) {
            'AUTHENTICATION_REQUIRED', 'AUTHENTICATION', 'TOKEN_EXPIRED' => ConnectionErrorCategory::Authentication,
            'AUTHORIZATION', 'PERMISSION_DENIED' => ConnectionErrorCategory::Authorization,
            'RATE_LIMIT', 'RATE_LIMITED', 'QUOTA_TEMPORARILY_EXCEEDED' => ConnectionErrorCategory::RateLimit,
            'NETWORK_ERROR', 'DNS_FAILURE', 'CONNECTION_RESET' => ConnectionErrorCategory::Network,
            'TIMEOUT', 'REQUEST_TIMEOUT' => ConnectionErrorCategory::Timeout,
            'PROVIDER_UNAVAILABLE', 'SERVICE_UNAVAILABLE' => ConnectionErrorCategory::ProviderUnavailable,
            'SQLSTATE', 'DATABASE_ERROR' => ConnectionErrorCategory::DatabaseError,
            'SCHEMA_CHANGED' => ConnectionErrorCategory::SchemaChanged,
            'VALIDATION_ERROR' => ConnectionErrorCategory::ValidationError,
            'RESOURCE_MISSING', 'NOT_FOUND' => ConnectionErrorCategory::ResourceMissing,
            default => ConnectionErrorCategory::Unknown,
        };
    }

    public function shouldRetry(ConnectionErrorCategory $category, int $attempt): bool
    {
        if ($attempt >= 5) {
            return false;
        }

        return in_array($category, [
            ConnectionErrorCategory::RateLimit,
            ConnectionErrorCategory::Network,
            ConnectionErrorCategory::Timeout,
            ConnectionErrorCategory::ProviderUnavailable,
        ], true);
    }

    public function backoffSeconds(int $attempt): int
    {
        return min(900, (2 ** max(0, $attempt - 1)) * 30);
    }
}
