<?php

namespace App\Enums;

enum CustomerAccountStatus: string
{
    case Active = 'active';
    case Trial = 'trial';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
