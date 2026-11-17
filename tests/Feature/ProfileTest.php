<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user = User::factory()->create();
        $user->issueEmailVerificationCode(); // sisa kode dari email lama harus dibuang

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        // Email berubah: kode baru dikirim ke email baru dan user langsung diarahkan ke halaman verifikasi.
        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('verification.notice', absolute: false));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->email_verification_code);
        \Illuminate\Support\Facades\Notification::assertSentTo($user, \App\Notifications\VerifyEmailCode::class);
    }

    public function test_changing_only_the_name_does_not_reset_verification_or_send_a_code(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['name' => 'Nama Baru', 'email' => $user->email])
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account_permanently(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
