<?php

namespace Tests\Feature\Platform;

use App\Models\CustomerAccount;
use App\Models\PortalUser;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_portal_user_can_own_many_services(): void
    {
        [$user, $account] = $this->membership('owner');
        Tenant::create(['id' => 'workspace-one', 'company_name' => 'Workspace One', 'slug' => 'workspace-one', 'status' => 'active', 'customer_account_id' => $account->id]);
        Tenant::create(['id' => 'workspace-two', 'company_name' => 'Workspace Two', 'slug' => 'workspace-two', 'status' => 'active', 'customer_account_id' => $account->id]);
        Tenant::create(['id' => 'workspace-three', 'company_name' => 'Workspace Three', 'slug' => 'workspace-three', 'status' => 'active', 'customer_account_id' => $account->id]);

        $this->assertTrue($user->belongsToAccount($account));
        $this->assertCount(3, $account->tenants);
    }

    public function test_one_portal_user_can_belong_to_multiple_accounts(): void
    {
        $user = PortalUser::factory()->create();
        $first = CustomerAccount::factory()->create();
        $second = CustomerAccount::factory()->create();
        $first->users()->attach($user, $this->pivot('owner'));
        $second->users()->attach($user, $this->pivot('billing'));

        $this->assertCount(2, $user->accounts);
    }

    public function test_one_account_can_contain_multiple_portal_users(): void
    {
        $account = CustomerAccount::factory()->create();
        $account->users()->attach(PortalUser::factory()->count(3)->create(), $this->pivot('member'));

        $this->assertCount(3, $account->users);
    }

    private function membership(string $role): array
    {
        $user = PortalUser::factory()->create();
        $account = CustomerAccount::factory()->create(['primary_owner_user_id' => $user->id]);
        $account->users()->attach($user, $this->pivot($role));
        return [$user, $account];
    }

    private function pivot(string $role): array
    {
        return ['role' => $role, 'can_manage_services' => true, 'can_manage_billing' => true, 'can_manage_members' => true, 'can_manage_support' => true, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()];
    }
}
