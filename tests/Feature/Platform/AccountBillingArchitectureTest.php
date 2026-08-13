<?php

namespace Tests\Feature\Platform;

use App\Models\BillingProfile;
use App\Models\CustomerAccount;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Platform\CustomerPortalService;
use App\Services\Platform\InvoiceService;
use App\Services\Platform\SubscriptionChangeService;
use App\Services\Platform\CouponService;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Services\Platform\InvoicePdfService;
use App\Services\Platform\PaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBillingArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_supports_multiple_subscriptions_and_normalized_mrr(): void
    {
        [$account, $plan, $user] = $this->billingContext();
        $monthly = $this->subscription($account, $plan, 'monthly', 'monthly-workspace');
        $yearly = $this->subscription($account, $plan, 'yearly', 'yearly-workspace');

        $billing = app(CustomerPortalService::class)->billing($account);
        $this->assertCount(2, $billing['subscriptions']);
        $this->assertSame(100.0, $billing['monthlyRecurring']);
    }

    public function test_invoice_service_creates_service_and_consolidated_invoices_with_snapshots(): void
    {
        [$account, $plan] = $this->billingContext();
        $first = $this->subscription($account, $plan, 'monthly', 'first-service');
        $second = $this->subscription($account, $plan, 'monthly', 'second-service');
        $service = app(InvoiceService::class)->create([
            'customer_account_id' => $account->id, 'tenant_id' => $first->tenant_id,
            'status' => 'open', 'currency' => 'USD', 'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Growth monthly', 'quantity' => 1, 'unit_amount' => 50, 'subscription_id' => $first->id, 'plan_id' => $plan->id]],
        ]);
        $consolidated = app(InvoiceService::class)->create([
            'customer_account_id' => $account->id, 'status' => 'open', 'currency' => 'USD', 'issued_on' => now()->toDateString(),
            'items' => [
                ['tenant_id' => $first->tenant_id, 'description' => 'First', 'quantity' => 1, 'unit_amount' => 50],
                ['tenant_id' => $second->tenant_id, 'description' => 'Second', 'quantity' => 1, 'unit_amount' => 50],
            ],
        ]);

        $this->assertSame($first->tenant_id, $service->tenant_id);
        $this->assertNull($consolidated->tenant_id);
        $this->assertSame('100.00', $consolidated->total);
        $this->assertSame('Acme Billing', $consolidated->billing_snapshot['billing_name']);
    }

    public function test_plan_changes_are_historical_and_can_be_scheduled(): void
    {
        [$account, $plan, $user] = $this->billingContext();
        $subscription = $this->subscription($account, $plan, 'monthly', 'change-service');
        $newPlan = Plan::factory()->create(['monthly_price' => 80, 'annual_price' => 800, 'is_active' => true, 'is_public' => true]);

        app(SubscriptionChangeService::class)->change($subscription, $newPlan, 'yearly', 'period_end', $user, 'Upgrade');

        $this->assertSame($plan->id, $subscription->refresh()->plan_id);
        $this->assertSame($newPlan->id, $subscription->pending_plan_id);
        $this->assertDatabaseHas('subscription_events', ['subscription_id' => $subscription->id, 'type' => 'plan_changed', 'old_plan_id' => $plan->id, 'new_plan_id' => $newPlan->id]);
    }

    public function test_coupon_redemption_is_plan_scoped_and_preserved_on_invoice_items(): void
    {
        [$account, $plan] = $this->billingContext();
        $subscription = $this->subscription($account, $plan, 'monthly', 'coupon-service');
        $coupon = Coupon::create([
            'code' => 'SAVE20', 'name' => 'Launch discount', 'type' => 'percent', 'value' => 20,
            'max_redemptions' => 10, 'redemptions' => 0, 'is_active' => true,
            'metadata' => ['duration' => 'once', 'currency' => 'USD'],
        ]);
        $coupon->plans()->attach($plan);
        app(CouponService::class)->apply($subscription, 'save20');

        $invoice = app(InvoiceService::class)->create([
            'customer_account_id' => $account->id, 'tenant_id' => $subscription->tenant_id,
            'status' => 'open', 'currency' => 'USD', 'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Discounted plan', 'quantity' => 1, 'unit_amount' => 50, 'subscription_id' => $subscription->id, 'plan_id' => $plan->id]],
        ]);

        $this->assertSame('10.00', $invoice->discount_total);
        $this->assertSame('40.00', $invoice->total);
        $this->assertSame('10.00', $invoice->items()->firstOrFail()->discount_total);
        $this->assertDatabaseHas('coupon_redemptions', ['coupon_id' => $coupon->id, 'invoice_id' => $invoice->id, 'status' => 'redeemed']);
        $this->assertNull($subscription->refresh()->coupon_id);
    }

    public function test_payment_attempts_are_idempotent_and_invoice_pdf_is_downloadable_content(): void
    {
        [$account, $plan] = $this->billingContext();
        $subscription = $this->subscription($account, $plan, 'monthly', 'pay-service');
        $invoice = app(InvoiceService::class)->create([
            'customer_account_id' => $account->id, 'tenant_id' => $subscription->tenant_id,
            'status' => 'open', 'currency' => 'USD', 'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Growth plan', 'quantity' => 1, 'unit_amount' => 50]],
        ]);

        $first = app(PaymentAttemptService::class)->forInvoice($account, $invoice, 'same-click');
        $second = app(PaymentAttemptService::class)->forInvoice($account, $invoice, 'same-click');
        $pdf = app(InvoicePdfService::class)->make($invoice->load(['items', 'customerAccount']));

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Invoice '.$invoice->number, $pdf);
    }

    private function billingContext(): array
    {
        $user = PortalUser::factory()->create();
        $account = CustomerAccount::factory()->create(['primary_owner_user_id' => $user->id]);
        $account->users()->attach($user, ['role' => 'owner', 'can_manage_services' => true, 'can_manage_billing' => true, 'can_manage_members' => true, 'can_manage_support' => true, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        BillingProfile::create(['customer_account_id' => $account->id, 'billing_name' => 'Acme Billing', 'billing_email' => 'billing@acme.test', 'currency' => 'USD']);
        $plan = Plan::factory()->create(['monthly_price' => 50, 'annual_price' => 600, 'currency' => 'USD', 'is_active' => true, 'is_public' => true]);
        return [$account, $plan, $user];
    }

    private function subscription(CustomerAccount $account, Plan $plan, string $interval, string $tenantId): Subscription
    {
        $tenant = Tenant::create(['id' => $tenantId, 'company_name' => $tenantId, 'slug' => $tenantId, 'status' => 'active', 'customer_account_id' => $account->id, 'plan_id' => $plan->id]);
        return Subscription::create(['customer_account_id' => $account->id, 'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => $interval, 'current_period_starts_at' => now(), 'current_period_ends_at' => now()->addMonth()]);
    }
}
