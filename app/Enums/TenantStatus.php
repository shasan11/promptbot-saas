<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case DatabaseCreating = 'database_creating';
    case DatabaseCreated = 'database_created';
    case Migrating = 'migrating';
    case Seeding = 'seeding';
    case Active = 'active';
    case Suspended = 'suspended';
    case Failed = 'failed';
    case Deleting = 'deleting';
    case Deleted = 'deleted';
}
