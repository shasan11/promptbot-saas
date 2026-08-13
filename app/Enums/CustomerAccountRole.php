<?php

namespace App\Enums;

enum CustomerAccountRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Billing = 'billing';
    case Member = 'member';
    case Viewer = 'viewer';
}
