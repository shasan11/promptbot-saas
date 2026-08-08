<?php

namespace App\Enums\Connections;

enum Environment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
    case Staging = 'staging';
    case Development = 'development';
}
