<?php

namespace App\Enums\AI;

enum ProviderStatus: string
{
    case Untested = 'untested';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';
    case AuthenticationFailed = 'authentication_failed';
}
