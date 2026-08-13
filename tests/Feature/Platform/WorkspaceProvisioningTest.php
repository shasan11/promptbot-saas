<?php

namespace Tests\Feature\Platform;

use App\Models\CustomerAccount;
use App\Models\PortalUser;
use App\Models\Tenant;
use App\Services\Platform\WorkspacePurchaseService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkspaceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_retried_workspace_purchase_is_idempotent(): void
    {
        $user = PortalUser::factory()->create();
        $account = CustomerAccount::factory()->create(['primary_owner_user_id' => $user->id]);
        $tenant = Tenant::create([
            'id' => 'second-workspace',
            'company_name' => 'Second Workspace',
            'slug' => 'second-workspace',
            'status' => 'active',
            'customer_account_id' => $account->id,
        ]);

        $provisioning = Mockery::mock(TenantProvisioningService::class);
        $provisioning->shouldReceive('provision')->once()->andReturn($tenant);
        $service = new WorkspacePurchaseService($provisioning);
        $payload = ['workspace_name' => 'Second Workspace', 'slug' => 'second-workspace'];

        $first = $service->purchase($account, $user, $payload, 'purchase-123');
        $retried = $service->purchase($account, $user, $payload, 'purchase-123');

        $this->assertSame($first->id, $retried->id);
        $this->assertDatabaseCount('workspace_purchase_requests', 1);
        $this->assertDatabaseHas('workspace_purchase_requests', [
            'customer_account_id' => $account->id,
            'tenant_id' => $tenant->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseCount('customer_account_activities', 1);
    }
}
