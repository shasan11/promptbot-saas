<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Platform\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create([
            'id' => 'payment-test-tenant',
            'company_name' => 'Payment Test Tenant',
            'slug' => 'payment-test-tenant',
            'status' => 'active',
        ]);
    }

    public function test_partial_payments_and_refunds_reconcile_invoice_status(): void
    {
        $tenant = $this->tenant();
        $invoice = app(InvoiceService::class)->create([
            'tenant_id' => $tenant->id,
            'status' => 'open',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Subscription', 'quantity' => 1, 'unit_amount' => 100]],
        ]);
        $admin = $this->centralAdminWithPermissions(['payments.view', 'payments.manage']);

        foreach ([40, 60] as $index => $amount) {
            $this->actingAs($admin, 'central')->post(route('superadmin.billing.payments.store'), [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'provider' => 'manual',
                'provider_reference' => 'PAY-'.($index + 1),
                'status' => 'paid',
                'amount' => $amount,
                'currency' => 'USD',
            ])->assertRedirect();
        }

        $this->assertSame('paid', $invoice->fresh()->status);

        $payment = Payment::query()->where('provider_reference', 'PAY-1')->firstOrFail();
        $this->actingAs($admin, 'central')->post(route('superadmin.billing.payments.refund', $payment), [
            'amount' => 10,
            'reason' => 'Adjustment',
        ])->assertRedirect();

        $this->assertSame('open', $invoice->fresh()->status);
        $this->assertSame('partially_refunded', $payment->fresh()->status);
    }

    public function test_payment_invoice_must_belong_to_selected_tenant(): void
    {
        $tenant = $this->tenant();
        $other = Tenant::create([
            'id' => 'other-payment-tenant',
            'company_name' => 'Other Tenant',
            'slug' => 'other-payment-tenant',
            'status' => 'active',
        ]);
        $invoice = app(InvoiceService::class)->create([
            'tenant_id' => $other->id,
            'status' => 'open',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_amount' => 10]],
        ]);

        $this->actingAs($this->centralAdminWithPermissions(['payments.manage']), 'central')
            ->post(route('superadmin.billing.payments.store'), [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'provider' => 'manual',
                'status' => 'paid',
                'amount' => 10,
                'currency' => 'USD',
            ])
            ->assertSessionHasErrors('invoice_id');
    }
}
