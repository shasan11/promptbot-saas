<?php

namespace App\Enums\Knowledge;

enum AccessLevel: string
{
    case Read = 'read';
    case Contribute = 'contribute';
    case Manage = 'manage';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Can read',
            self::Contribute => 'Can add content',
            self::Manage => 'Can manage',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Read => 1,
            self::Contribute => 2,
            self::Manage => 3,
        };
    }

    public function allows(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }
}
