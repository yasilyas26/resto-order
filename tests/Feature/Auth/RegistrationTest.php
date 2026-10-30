<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'email' => 'test@gmail.com',
            'password' => 'KopiSusu2026x',
            'password_confirmation' => 'KopiSusu2026x',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_new_account_starts_unverified_and_gets_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@gmail.com',
            'password' => 'KopiSusu2026x',
            'password_confirmation' => 'KopiSusu2026x',
        ]);

        $user = User::where('email', 'test@gmail.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_an_unverified_account_is_sent_to_the_verify_email_page(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'belum@gmail.com']);

        foreach (['/dashboard', '/profile'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect(route('verification.notice', absolute: false));
        }

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_the_verification_link_marks_the_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'belum@gmail.com']);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($url)->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_only_gmail_addresses_can_register(): void
    {
        foreach (['orang@yahoo.com', 'orang@example.com', 'orang@gmail.com.evil.test', 'orang@mail.gmail.com'] as $email) {
            $this->post('/register', [
                'name' => 'Test User',
                'email' => $email,
                'password' => 'KopiSusu2026x',
                'password_confirmation' => 'KopiSusu2026x',
            ])->assertSessionHasErrors(['email' => 'Pendaftaran hanya bisa memakai email Gmail (@gmail.com).']);

            $this->assertGuest();
            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_register_is_limited_to_five_attempts_per_five_minutes(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/register', ['email' => "bukan-email-$i"])->assertSessionHasErrors();
        }

        $this->post('/register', ['email' => 'bukan-email-6'])->assertStatus(429);
    }
}
