<?php

namespace App\Jobs\Connections;

use App\Jobs\Concerns\TenantAware;
use App\Models\Connections\Connection;
use App\Models\Connections\DataSource;
use App\Models\User;
use App\Services\Connections\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunConnectionSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $connectionId, public ?int $dataSourceId = null, public ?int $actorId = null, public string $trigger = 'manual')
    {
        $this->captureTenant();
        $this->onQueue('connections-sync');
    }

    public function handle(SyncService $service): void
    {
        $connection = Connection::findOrFail($this->connectionId);
        $dataSource = $this->dataSourceId ? DataSource::findOrFail($this->dataSourceId) : null;
        $actor = $this->actorId ? User::find($this->actorId) : null;

        $service->run($connection, $dataSource, $actor, $this->trigger);
    }
}
