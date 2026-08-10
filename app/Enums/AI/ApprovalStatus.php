<?php

namespace App\Enums\AI;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Executed = 'executed';
}
