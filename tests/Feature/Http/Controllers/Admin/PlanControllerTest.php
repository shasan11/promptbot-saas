<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_admin_can_view_plans(): void
    {
        $this->actingAs(CentralUser::factory()->create(), 'central')
            ->get(route('superadmin.plans.index'))
            ->assertOk();
    }
}
