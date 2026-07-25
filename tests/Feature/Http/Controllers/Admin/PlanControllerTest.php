<?php

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_admin_can_view_plans(): void
    {
        $this->actingAs($this->centralUserWithPermissions('plans.view'), 'central')
            ->get(route('superadmin.plans.index'))
            ->assertOk();
    }
}
