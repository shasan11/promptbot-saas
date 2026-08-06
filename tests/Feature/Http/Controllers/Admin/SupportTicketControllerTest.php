<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create([
            'id' => 'ticket-test-tenant',
            'company_name' => 'Ticket Test Tenant',
            'slug' => 'ticket-test-tenant',
            'status' => 'active',
        ]);
    }

    public function test_ticket_can_be_created_updated_and_given_an_internal_note(): void
    {
        $tenant = $this->tenant();
        $admin = $this->centralAdminWithPermissions(['support.view', 'support.manage']);

        $this->actingAs($admin, 'central')->post(route('superadmin.tickets.store'), [
            'tenant_id' => $tenant->id,
            'subject' => 'Billing question',
            'description' => 'Please review the latest invoice.',
            'priority' => 'high',
            'requester_email' => 'owner@example.test',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^TKT-\d{6}$/', $ticket->number);

        $this->actingAs($admin, 'central')->put(route('superadmin.tickets.update', $ticket), [
            'status' => 'resolved',
            'priority' => 'normal',
        ])->assertRedirect();

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->actingAs($admin, 'central')->post(route('superadmin.tickets.messages.store', $ticket), [
            'body' => 'Verified against the payment ledger.',
            'is_internal' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'is_internal' => true,
        ]);
    }

    public function test_view_permission_does_not_allow_ticket_changes(): void
    {
        $ticket = SupportTicket::create([
            'number' => 'TKT-000001',
            'tenant_id' => $this->tenant()->id,
            'subject' => 'Read only',
            'description' => 'Read only ticket',
            'status' => 'open',
            'priority' => 'normal',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($this->centralAdminWithPermissions(['support.view']), 'central')
            ->put(route('superadmin.tickets.update', $ticket), ['status' => 'closed'])
            ->assertForbidden();
    }
}
