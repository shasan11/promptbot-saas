<?php

namespace App\Enums\AI;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
    case RateLimited = 'rate_limited';
}
