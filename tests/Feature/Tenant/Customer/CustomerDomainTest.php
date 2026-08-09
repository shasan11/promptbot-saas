<?php

namespace Tests\Feature\Tenant\Customer;

use App\Models\Customer\Contact;
use App\Models\Customer\CustomField;
use App\Models\Customer\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class CustomerDomainTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_customer_domain_is_tenant_isolated_authorized_and_validated_end_to_end(): void
    {
        [$tenantA, $domainA] = $this->createTenantWithDomain();
        [$tenantB, $domainB] = $this->createTenantWithDomain();
        [$adminA, $noAccessA] = $this->createTenantUsers($tenantA, [
            ['attributes' => ['name' => 'Customer Admin'], 'role' => 'Tenant Administrator'],
            ['attributes' => ['name' => 'No Customer Access'], 'role' => null],
        ]);
        $adminB = $this->createTenantUser($tenantB, ['name' => 'Other Tenant Admin']);

        tenancy()->initialize($tenantA);
        $tag = Tag::create(['name' => 'VIP Account', 'slug' => 'vip-account', 'color' => '#f59e0b', 'status' => 'active', 'created_by' => $adminA->id]);
        CustomField::create(['label' => 'Account tier', 'key' => 'account_tier', 'resource_type' => 'contact', 'field_type' => 'single_select', 'required' => true, 'options' => ['standard', 'premium'], 'active' => true, 'created_by' => $adminA->id]);
        tenancy()->end();

        $this->actingAs($noAccessA, 'tenant')->get("http://{$domainA}/customers/contacts")->assertForbidden();

        $response = $this->actingAs($adminA, 'tenant')->post("http://{$domainA}/customers/contacts", [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'display_name' => '',
            'email' => 'ada@example.test', 'status' => 'vip', 'source' => 'manual',
            'tag_ids' => [$tag->id], 'custom_fields' => ['account_tier' => 'premium'],
        ]);
        $response->assertRedirect();

        tenancy()->initialize($tenantA);
        $contactA = Contact::query()->where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame('Ada Lovelace', $contactA->display_name);
        $this->assertTrue($contactA->tags()->where('slug', 'vip-account')->exists());
        $this->assertSame('premium', $contactA->customFieldValues()->firstOrFail()->value);
        $this->assertTrue($contactA->activities()->where('event_type', 'contact.created')->exists());
        tenancy()->end();

        $this->actingAs($adminB, 'tenant')->get("http://{$domainB}/customers/contacts/{$contactA->public_uuid}")->assertNotFound();

        $this->actingAs($adminA, 'tenant')->post("http://{$domainA}/customers/contacts", [
            'display_name' => 'Invalid Tier', 'email' => 'invalid@example.test', 'status' => 'active',
            'custom_fields' => ['account_tier' => 'enterprise'],
        ])->assertSessionHasErrors('custom_fields.account_tier');

        tenancy()->initialize($tenantA);
        $this->assertFalse(Contact::query()->where('email', 'invalid@example.test')->exists(), 'The invalid contact must be rolled back transactionally.');
        tenancy()->end();
    }
}
