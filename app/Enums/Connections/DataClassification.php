<?php

namespace App\Enums\Connections;

enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
    case Regulated = 'regulated';
}
