<?php

namespace App\Jobs\Connections;

use App\Jobs\Concerns\TenantAware;
use App\Models\Connections\Connection;
use App\Models\User;
use App\Services\Connections\ResourceDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DiscoverConnectionResourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $connectionId, public ?int $actorId = null)
    {
        $this->captureTenant();
        $this->onQueue('connections-discovery');
    }

    public function handle(ResourceDiscoveryService $service): void
    {
        $connection = Connection::findOrFail($this->connectionId);
        $actor = $this->actorId ? User::find($this->actorId) : null;

        $service->discover($connection, $actor);
    }
}
