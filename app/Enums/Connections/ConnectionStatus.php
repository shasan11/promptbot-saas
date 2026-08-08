<?php

namespace App\Enums\Connections;

enum ConnectionStatus: string
{
    case Draft = 'draft';
    case Connecting = 'connecting';
    case Active = 'active';
    case Disabled = 'disabled';
    case NeedsAttention = 'needs_attention';
    case AuthenticationRequired = 'authentication_required';
    case Degraded = 'degraded';
    case RateLimited = 'rate_limited';
    case Error = 'error';
    case Disconnected = 'disconnected';
    case Archived = 'archived';
}
