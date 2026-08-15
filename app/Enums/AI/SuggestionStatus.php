<?php

namespace App\Enums\AI;

enum SuggestionStatus: string
{
    case Generated = 'generated';
    case Accepted = 'accepted';
    case Edited = 'edited';
    case Rejected = 'rejected';
    case Applied = 'applied';
    case Sent = 'sent';
    case Expired = 'expired';
    case Failed = 'failed';
}
