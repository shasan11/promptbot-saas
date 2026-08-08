<?php

namespace App\Services\Connections;

use App\Models\Connections\Connection;
use App\Models\Connections\DataSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncLockService
{
    public function acquire(Connection $connection, ?DataSource $dataSource = null): object
    {
        $key = 'conn_sync_'.sha1(tenant('id').':'.$connection->id.':'.($dataSource?->id ?? 'connection'));

        if (DB::connection()->getDriverName() === 'mysql') {
            $result = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$key]);

            if ((int) ($result->acquired ?? 0) !== 1) {
                throw new RuntimeException('A sync is already running for this connection or data source.');
            }

            return new class($key)
            {
                public function __construct(private readonly string $key) {}

                public function release(): void
                {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$this->key]);
                }
            };
        }

        return new class
        {
            public function release(): void {}
        };
    }
}
