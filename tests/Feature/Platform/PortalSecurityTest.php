<?php

namespace Tests\Feature\Platform;

use App\Models\CustomerAccount;
use App\Models\Invoice;
use App\Models\PortalUser;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_user_cannot_access_another_accounts_workspace_invoice_or_ticket(): void
    {
        [$userA, $accountA] = $this->member();
        [, $accountB] = $this->member();
        $workspaceB = Tenant::create(['id' => 'workspace-b', 'company_name' => 'Workspace B', 'slug' => 'workspace-b', 'status' => 'active', 'customer_account_id' => $accountB->id]);
        $invoiceB = Invoice::create(['customer_account_id' => $accountB->id, 'tenant_id' => $workspaceB->id, 'number' => 'INV-B', 'status' => 'open', 'subtotal' => 20, 'tax_total' => 0, 'total' => 20, 'currency' => 'USD']);
        $ticketB = SupportTicket::create(['customer_account_id' => $accountB->id, 'tenant_id' => $workspaceB->id, 'number' => 'TKT-B', 'subject' => 'Private', 'description' => 'Private', 'status' => 'open']);

        $this->actingAs($userA, 'portal')->withSession(['portal.active_customer_account_id' => $accountA->id]);
        $this->get(route('portal.workspaces.show', $workspaceB))->assertForbidden();
        $this->get(route('portal.billing.invoices.show', $invoiceB))->assertForbidden();
        $this->get(route('portal.support.show', $ticketB))->assertForbidden();
    }

    public function test_portal_identity_does_not_authenticate_as_superadmin(): void
    {
        [$user] = $this->member();
        $this->actingAs($user, 'portal')->get('/superadmin/dashboard')->assertRedirect('/superadmin/login');
    }

    public function test_selected_workspace_access_is_enforced_by_policy_not_only_navigation(): void
    {
        [$user, $account] = $this->member();
        $account->users()->updateExistingPivot($user->id, ['role' => 'member', 'service_access' => 'selected']);
        $allowed = Tenant::create(['id' => 'allowed-service', 'company_name' => 'Allowed', 'slug' => 'allowed-service', 'status' => 'active', 'customer_account_id' => $account->id]);
        $denied = Tenant::create(['id' => 'denied-service', 'company_name' => 'Denied', 'slug' => 'denied-service', 'status' => 'active', 'customer_account_id' => $account->id]);
        DB::table('customer_account_user_tenants')->insert([
            'customer_account_id' => $account->id, 'portal_user_id' => $user->id, 'tenant_id' => $allowed->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user, 'portal')->withSession(['portal.active_customer_account_id' => $account->id]);
        $this->get(route('portal.workspaces.show', $allowed))->assertOk();
        $this->get(route('portal.workspaces.show', $denied))->assertForbidden();
    }

    public function test_selected_workspace_member_cannot_open_consolidated_invoice_containing_an_ungranted_service(): void
    {
        [$user, $account] = $this->member();
        $account->users()->updateExistingPivot($user->id, ['role' => 'member', 'service_access' => 'selected']);
        $allowed = Tenant::create(['id' => 'invoice-allowed', 'company_name' => 'Allowed', 'slug' => 'invoice-allowed', 'status' => 'active', 'customer_account_id' => $account->id]);
        $denied = Tenant::create(['id' => 'invoice-denied', 'company_name' => 'Denied', 'slug' => 'invoice-denied', 'status' => 'active', 'customer_account_id' => $account->id]);
        DB::table('customer_account_user_tenants')->insert(['customer_account_id' => $account->id, 'portal_user_id' => $user->id, 'tenant_id' => $allowed->id, 'created_at' => now(), 'updated_at' => now()]);
        $invoice = Invoice::create(['customer_account_id' => $account->id, 'number' => 'INV-MIXED', 'status' => 'open', 'subtotal' => 40, 'tax_total' => 0, 'total' => 40, 'currency' => 'USD']);
        $invoice->items()->createMany([
            ['tenant_id' => $allowed->id, 'description' => 'Allowed', 'quantity' => 1, 'unit_amount' => 20, 'total' => 20],
            ['tenant_id' => $denied->id, 'description' => 'Denied', 'quantity' => 1, 'unit_amount' => 20, 'total' => 20],
        ]);

        $this->actingAs($user, 'portal')->withSession(['portal.active_customer_account_id' => $account->id]);
        $this->get(route('portal.billing.invoices.show', $invoice))->assertForbidden();
        $this->get(route('portal.billing.invoices.download', $invoice))->assertForbidden();
    }

    private function member(): array
    {
        $user = PortalUser::factory()->create();
        $account = CustomerAccount::factory()->create(['primary_owner_user_id' => $user->id]);
        $account->users()->attach($user, ['role' => 'owner', 'can_manage_services' => true, 'can_manage_billing' => true, 'can_manage_members' => true, 'can_manage_support' => true, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return [$user, $account];
    }
}
