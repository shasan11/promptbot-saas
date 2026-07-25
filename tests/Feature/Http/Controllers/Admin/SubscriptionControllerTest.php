<?php

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_admin_can_view_subscriptions(): void
    {
        $this->actingAs($this->centralUserWithPermissions('subscriptions.view'), 'central')
            ->get(route('superadmin.subscriptions.index'))
            ->assertOk();
    }
}
