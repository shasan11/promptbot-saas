<?php

namespace App\Jobs\Tenancy;

use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public readonly string $encryptedPayload;

    public function __construct(array $data)
    {
        $this->encryptedPayload = Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR));
        $this->onQueue('provisioning');
    }

    public function handle(TenantProvisioningService $provisioning): void
    {
        $data = json_decode(
            Crypt::decryptString($this->encryptedPayload),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $provisioning->provision($data);
    }
}
