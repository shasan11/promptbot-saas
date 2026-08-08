<?php

namespace App\Enums\Connections;

enum CredentialStatus: string
{
    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Rotated = 'rotated';
    case Missing = 'missing';
}
