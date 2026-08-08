<?php

namespace App\Enums\Connections;

enum ActionRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
