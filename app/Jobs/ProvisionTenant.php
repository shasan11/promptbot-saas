<?php

namespace App\Jobs;

use App\Models\PlatformOperation;
use App\Services\Platform\OperationService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $operationId,
        public readonly array $data,
    ) {}

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant-provision:'.($this->data['slug'] ?? $this->data['company_name']))];
    }

    public function handle(TenantProvisioningService $provisioning, OperationService $operations): void
    {
        $operation = PlatformOperation::findOrFail($this->operationId);

        try {
            $operations->markRunning($operation);
            $operations->progress($operation, 15, 'Provisioning tenant shell.');
            $tenant = $provisioning->provision($this->data);
            $operation->forceFill(['tenant_id' => $tenant->id])->save();
            $operations->complete($operation);
        } catch (Throwable $exception) {
            $operation->increment('retry_count');
            $operations->fail($operation, $exception->getMessage());

            throw $exception;
        }
    }
}
