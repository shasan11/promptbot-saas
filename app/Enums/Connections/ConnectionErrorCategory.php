<?php

namespace App\Enums\Connections;

enum ConnectionErrorCategory: string
{
    case Authentication = 'authentication';
    case Authorization = 'authorization';
    case RateLimit = 'rate_limit';
    case Network = 'network';
    case Timeout = 'timeout';
    case InvalidConfiguration = 'invalid_configuration';
    case ProviderUnavailable = 'provider_unavailable';
    case SchemaChanged = 'schema_changed';
    case ResourceMissing = 'resource_missing';
    case CredentialExpired = 'credential_expired';
    case WebhookFailure = 'webhook_failure';
    case DatabaseError = 'database_error';
    case ValidationError = 'validation_error';
    case QuotaExceeded = 'quota_exceeded';
    case Unknown = 'unknown';
}
