<?php

namespace App\Enums\Knowledge;

enum KnowledgeVisibility: string
{
    case Private = 'private';
    case Teams = 'teams';
    case Workspace = 'workspace';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Teams => 'Specific teams',
            self::Workspace => 'Workspace-wide',
        };
    }
}
