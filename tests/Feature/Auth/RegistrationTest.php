<?php

namespace Tests\Feature\Auth;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_public_registration_cannot_create_an_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // No POST route exists for /register (only the public-page GET catch-all
        // does), so Laravel correctly reports 405 rather than 404 here. Either
        // way, no account is created and nobody is authenticated.
        $this->assertContains($response->status(), [404, 405]);
        $this->assertGuest('central');
        $this->assertDatabaseMissing('central_users', ['email' => 'test@example.com']);
    }

    public function test_welcome_page_does_not_advertise_registration(): void
    {
        CentralUser::factory()->create();

        $this->get('/')->assertInertia(fn ($page) => $page->where('canRegister', false));
    }
}
