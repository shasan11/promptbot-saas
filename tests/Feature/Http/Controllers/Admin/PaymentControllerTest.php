<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\BillingProfile;
use App\Models\CustomerAccount;
use App\Models\Payment;
use App\Models\PortalUser;
use App\Models\Tenant;
use App\Services\Platform\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

/**
 * Payments hang off a customer account, not a workspace: one account can own
 * several workspaces and be invoiced for them together, so every relationship
 * check here is anchored on the account first and the workspace second.
 */
class PaymentControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    private function account(): CustomerAccount
    {
        $user = PortalUser::factory()->create();
        $account = CustomerAccount::factory()->create(['primary_owner_user_id' => $user->id]);

        BillingProfile::create([
            'customer_account_id' => $account->id,
            'billing_name' => 'Payment Test Billing',
            'billing_email' => 'billing@payment-test.test',
            'currency' => 'USD',
        ]);

        return $account;
    }

    private function tenant(CustomerAccount $account, string $id = 'payment-test-tenant'): Tenant
    {
        return Tenant::create([
            'id' => $id,
            'company_name' => 'Payment Test Tenant',
            'slug' => $id,
            'status' => 'active',
            'customer_account_id' => $account->id,
        ]);
    }

    public function test_partial_payments_and_refunds_reconcile_invoice_status(): void
    {
        $account = $this->account();
        $tenant = $this->tenant($account);
        $invoice = app(InvoiceService::class)->create([
            'customer_account_id' => $account->id,
            'tenant_id' => $tenant->id,
            'status' => 'open',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Subscription', 'quantity' => 1, 'unit_amount' => 100]],
        ]);
        $admin = $this->centralAdminWithPermissions(['payments.view', 'payments.manage']);

        // Two partial payments settle the invoice between them.
        foreach ([40, 60] as $index => $amount) {
            $this->actingAs($admin, 'central')->post(route('superadmin.billing.payments.store'), [
                'customer_account_id' => $account->id,
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'provider' => 'manual',
                'provider_reference' => 'PAY-'.($index + 1),
                'status' => 'paid',
                'amount' => $amount,
                'currency' => 'USD',
            ])->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->assertSame('paid', $invoice->fresh()->status);

        // A refund takes the invoice back below its total, so it must reopen —
        // leaving it 'paid' would hide money still owed.
        $payment = Payment::query()->where('provider_reference', 'PAY-1')->firstOrFail();
        $this->actingAs($admin, 'central')->post(route('superadmin.billing.payments.refund', $payment), [
            'amount' => 10,
            'reason' => 'Adjustment',
            // Refunds are idempotent per key: a double-submitted refund form
            // must not move money twice.
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('open', $invoice->fresh()->status);
        $this->assertSame('partially_refunded', $payment->fresh()->status);
    }

    public function test_payment_invoice_must_belong_to_the_selected_workspace(): void
    {
        $account = $this->account();
        $tenant = $this->tenant($account);
        $other = $this->tenant($account, 'other-payment-tenant');

        // Same account, different workspace: the account check passes and the
        // workspace check is the one that has to catch this.
        $invoice = app(InvoiceService::class)->create([
            'customer_account_id' => $account->id,
            'tenant_id' => $other->id,
            'status' => 'open',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_amount' => 10]],
        ]);

        $this->actingAs($this->centralAdminWithPermissions(['payments.manage']), 'central')
            ->post(route('superadmin.billing.payments.store'), [
                'customer_account_id' => $account->id,
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'provider' => 'manual',
                'status' => 'paid',
                'amount' => 10,
                'currency' => 'USD',
            ])
            ->assertSessionHasErrors('invoice_id');
    }

    public function test_a_payment_cannot_be_recorded_against_another_accounts_workspace(): void
    {
        $account = $this->account();
        $otherAccount = $this->account();
        $foreignTenant = $this->tenant($otherAccount, 'foreign-payment-tenant');

        $this->actingAs($this->centralAdminWithPermissions(['payments.manage']), 'central')
            ->post(route('superadmin.billing.payments.store'), [
                'customer_account_id' => $account->id,
                'tenant_id' => $foreignTenant->id,
                'provider' => 'manual',
                'status' => 'paid',
                'amount' => 10,
                'currency' => 'USD',
            ])
            ->assertSessionHasErrors('tenant_id');
    }
}
