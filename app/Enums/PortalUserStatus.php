<?php

namespace App\Enums;

enum PortalUserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
}
