<?php

namespace App\Jobs;

use App\Models\PlatformOperation;
use App\Models\Tenant;
use App\Services\Platform\OperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class RunTenantSeeders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $operationId, public readonly string $tenantId) {}

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant-seed:'.$this->tenantId)];
    }

    public function handle(OperationService $operations): void
    {
        $operation = PlatformOperation::findOrFail($this->operationId);

        try {
            Tenant::findOrFail($this->tenantId);
            $operations->markRunning($operation);
            Artisan::call('tenants:seed', ['--tenants' => [$this->tenantId], '--class' => 'Database\\Seeders\\TenantDatabaseSeeder', '--force' => true]);
            $operations->progress($operation, 90, Artisan::output());
            $operations->complete($operation);
        } catch (Throwable $exception) {
            $operation->increment('retry_count');
            $operations->fail($operation, $exception->getMessage());

            throw $exception;
        }
    }
}
