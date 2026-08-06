<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Platform\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    private function tenant(string $suffix = ''): Tenant
    {
        return Tenant::create([
            'id' => 'invoice-test-tenant'.$suffix,
            'company_name' => 'Invoice Test Tenant'.$suffix,
            'slug' => 'invoice-test-tenant'.$suffix,
            'status' => 'active',
        ]);
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.billing.invoices.index'))
            ->assertForbidden();
    }

    public function test_view_permission_does_not_grant_create_access(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['invoices.view']), 'central')
            ->get(route('superadmin.billing.invoices.create'))
            ->assertForbidden();
    }

    public function test_admin_with_manage_permission_can_create_an_invoice_with_computed_totals(): void
    {
        $tenant = $this->tenant();

        $response = $this->actingAs($this->centralAdminWithPermissions(['invoices.manage', 'invoices.view']), 'central')
            ->post(route('superadmin.billing.invoices.store'), [
                'tenant_id' => $tenant->id,
                'status' => 'open',
                'currency' => 'usd',
                'issued_on' => now()->toDateString(),
                'due_on' => now()->addDays(14)->toDateString(),
                'tax_total' => 5,
                'items' => [
                    ['description' => 'Setup fee', 'quantity' => 1, 'unit_amount' => 100],
                    ['description' => 'Seats', 'quantity' => 3, 'unit_amount' => 10],
                ],
            ]);

        $invoice = Invoice::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $response->assertRedirect(route('superadmin.billing.invoices.show', $invoice->id));
        $this->assertSame('USD', $invoice->currency);
        $this->assertSame('130.00', (string) $invoice->subtotal);
        $this->assertSame('135.00', (string) $invoice->total);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertMatchesRegularExpression('/^INV-\d{6}$/', $invoice->number);
    }

    public function test_invoice_numbers_are_sequential(): void
    {
        $tenant = $this->tenant();
        $admin = $this->centralAdminWithPermissions(['invoices.manage']);

        $payload = fn () => [
            'tenant_id' => $tenant->id,
            'status' => 'draft',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_amount' => 10]],
        ];

        $this->actingAs($admin, 'central')->post(route('superadmin.billing.invoices.store'), $payload());
        $this->actingAs($admin, 'central')->post(route('superadmin.billing.invoices.store'), $payload());

        $numbers = Invoice::query()->orderBy('number')->pluck('number')->all();
        $this->assertSame(['INV-000001', 'INV-000002'], $numbers);
    }

    public function test_marking_an_invoice_paid_records_paid_at(): void
    {
        $invoice = app(InvoiceService::class)->create([
            'tenant_id' => $this->tenant()->id,
            'status' => 'open',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_amount' => 10]],
        ]);

        $this->actingAs($this->centralAdminWithPermissions(['invoices.manage']), 'central')
            ->post(route('superadmin.billing.invoices.mark-paid', $invoice))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_a_paid_invoice_cannot_be_marked_paid_again(): void
    {
        $invoice = app(InvoiceService::class)->create([
            'tenant_id' => $this->tenant()->id,
            'status' => 'open',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_amount' => 10]],
        ]);
        app(InvoiceService::class)->markPaid($invoice);

        $this->actingAs($this->centralAdminWithPermissions(['invoices.manage']), 'central')
            ->post(route('superadmin.billing.invoices.mark-paid', $invoice))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_voiding_an_invoice_records_voided_at(): void
    {
        $invoice = app(InvoiceService::class)->create([
            'tenant_id' => $this->tenant()->id,
            'status' => 'draft',
            'currency' => 'USD',
            'issued_on' => now()->toDateString(),
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_amount' => 10]],
        ]);

        $this->actingAs($this->centralAdminWithPermissions(['invoices.manage']), 'central')
            ->post(route('superadmin.billing.invoices.void', $invoice))
            ->assertRedirect();

        $this->assertSame('void', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->voided_at);
    }
}
