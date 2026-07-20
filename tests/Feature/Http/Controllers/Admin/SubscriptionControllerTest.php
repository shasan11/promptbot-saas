<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_admin_can_view_subscriptions(): void
    {
        $this->actingAs(CentralUser::factory()->create(), 'central')
            ->get(route('superadmin.subscriptions.index'))
            ->assertOk();
    }
}
