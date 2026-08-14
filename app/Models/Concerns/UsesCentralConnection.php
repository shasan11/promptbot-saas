<?php

namespace App\Models\Concerns;

/**
 * Pins a model to the central database connection regardless of whatever
 * connection is currently "default" — which stancl/tenancy swaps to the
 * tenant's own database for the duration of tenancy()->initialize(). Models
 * using this trait represent central-only data (platform config, AI provider
 * credentials, usage logs) that is nonetheless read or written from tenant
 * request/job context, so relying on the ambient default connection would
 * silently (or not so silently — missing-table errors) break those reads.
 */
trait UsesCentralConnection
{
    public function __construct(array $attributes = [])
    {
        $this->connection = config('tenancy.database.central_connection', 'central');

        parent::__construct($attributes);
    }
}
