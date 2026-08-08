<?php

namespace App\Enums\Connections;

enum SyncMode: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case Incremental = 'incremental';
    case Delta = 'delta';
    case Webhook = 'webhook';
    case Realtime = 'realtime';
}
