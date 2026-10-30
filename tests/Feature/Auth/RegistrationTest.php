<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'KopiSusu2026x',
            'password_confirmation' => 'KopiSusu2026x',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_register_is_limited_to_five_attempts_per_five_minutes(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/register', ['email' => "bukan-email-$i"])->assertSessionHasErrors();
        }

        $this->post('/register', ['email' => 'bukan-email-6'])->assertStatus(429);
    }
}
