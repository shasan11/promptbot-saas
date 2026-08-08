<?php

namespace App\Enums\Connections;

enum ConnectionHealth: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case NeedsAttention = 'needs_attention';
    case AuthenticationExpired = 'authentication_expired';
    case RateLimited = 'rate_limited';
    case Disconnected = 'disconnected';
    case Disabled = 'disabled';
    case Error = 'error';
    case Unknown = 'unknown';
}
