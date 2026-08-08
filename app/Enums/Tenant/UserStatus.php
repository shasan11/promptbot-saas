<?php

namespace App\Enums\Tenant;

enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Deactivated => 'Deactivated',
            self::Expired => 'Expired',
        };
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Active],
            self::Active => [self::Suspended, self::Deactivated],
            self::Suspended => [self::Active, self::Deactivated],
            self::Deactivated => [self::Active],
            self::Expired => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
