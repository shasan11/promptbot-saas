<?php

namespace Tests\Feature\Tenant\Administration;

use App\Models\TenantInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_administrator_can_invite_a_user_and_accepting_creates_an_account(): void
    {
        Notification::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Inviting Admin'], 'Tenant Administrator');

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/administration/invitations", [
                'email' => 'newperson@example.test',
                'name' => 'New Person',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $invitation = TenantInvitation::where('email', 'newperson@example.test')->firstOrFail();
        $this->assertEquals('pending', $invitation->status);
        tenancy()->end();

        // The job dispatches synchronously in tests (QUEUE_CONNECTION=sync);
        // recover the plain token the same way the emailed link would carry it
        // by re-deriving it isn't possible (only the hash is stored), so we
        // simulate acceptance using a freshly generated token/hash pair tied
        // to the same invitation row instead.
        [$plainToken, $tokenHash] = TenantInvitation::generateToken();
        tenancy()->initialize($tenant);
        $invitation->forceFill(['token_hash' => $tokenHash])->save();
        tenancy()->end();

        // The admin's actingAs() session would otherwise persist into this
        // request, tripping the accept route's guest:tenant middleware before
        // it ever reaches the controller.
        $this->app['auth']->forgetGuards();

        $this->post("http://{$domain}/invitation/{$plainToken}/accept", [
            'name' => 'New Person',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ])->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('users', ['email' => 'newperson@example.test', 'status' => 'active']);
        $this->assertEquals('accepted', TenantInvitation::find($invitation->id)->status);
        tenancy()->end();
    }

    public function test_invitation_token_from_one_tenant_is_rejected_on_another_tenants_domain(): void
    {
        [$tenantA, $domainA] = $this->createTenantWithDomain();
        [$tenantB, $domainB] = $this->createTenantWithDomain();

        [$plainToken, $tokenHash] = TenantInvitation::generateToken();

        tenancy()->initialize($tenantA);
        TenantInvitation::create([
            'email' => 'cross-tenant@example.test',
            'token_hash' => $tokenHash,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);
        tenancy()->end();

        // The same token, presented on tenant B's domain, must not resolve —
        // tenant B's own (empty) invitations table is what gets queried.
        $response = $this->get("http://{$domainB}/invitation/{$plainToken}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('invitation.valid', false));
    }
}
