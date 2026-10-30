<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_staff_and_admin_cannot_log_in_from_the_public_login_page(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->admin()->create()] as $internal) {
            $this->post('/login', ['email' => $internal->email, 'password' => 'password'])
                ->assertSessionHasErrors(['email' => trans('auth.failed')]);

            $this->assertGuest();
        }
    }

    public function test_internal_accounts_tried_on_the_public_login_page_count_toward_the_lockout(): void
    {
        $staff = User::factory()->staff()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $staff->email, 'password' => 'password']);
        }

        // Percobaan ke-6 dikunci, walaupun passwordnya benar.
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_login_is_limited_to_five_attempts_per_ip_even_with_different_emails(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => "orang$i@example.com", 'password' => 'salah'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'orang6@example.com', 'password' => 'salah'])
            ->assertStatus(429);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
