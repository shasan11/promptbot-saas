<?php

namespace App\Enums\Connections;

enum SyncStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Paused = 'paused';
    case Retrying = 'retrying';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RateLimited = 'rate_limited';
    case WaitingForAuth = 'waiting_for_auth';
}
