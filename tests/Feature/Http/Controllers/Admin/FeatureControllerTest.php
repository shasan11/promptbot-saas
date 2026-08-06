<?php

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class FeatureControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.features.index'))
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_can_view_features(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['features.view']), 'central')
            ->get(route('superadmin.features.index'))
            ->assertOk();
    }

    public function test_view_permission_does_not_grant_manage_access(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['features.view']), 'central')
            ->get(route('superadmin.features.create'))
            ->assertForbidden();
    }

    public function test_admin_with_manage_permission_can_create_a_feature(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['features.manage']), 'central')
            ->post(route('superadmin.features.store'), [
                'name' => 'API Calls',
                'code' => 'api_calls',
                'type' => 'limited',
            ])
            ->assertRedirect(route('superadmin.features.index'));

        $this->assertDatabaseHas('features', ['code' => 'api_calls']);
    }
}
