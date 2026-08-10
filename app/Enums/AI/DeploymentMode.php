<?php

namespace App\Enums\AI;

enum DeploymentMode: string
{
    case Copilot = 'copilot';
    case DraftOnly = 'draft_only';
    case Autonomous = 'autonomous';
}
