<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertOk()->assertSee('kode 6 digit');
    }

    public function test_email_can_be_verified_with_the_right_code(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $code = $user->issueEmailVerificationCode();

        $response = $this->actingAs($user)->post('/verify-email', ['code' => $code]);

        Event::assertDispatched(Verified::class);
        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->email_verification_code);
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_the_code_is_stored_hashed_not_as_plain_text(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueEmailVerificationCode();

        $stored = $user->fresh()->email_verification_code;
        $this->assertNotSame($code, $stored);
        $this->assertTrue(Hash::check($code, $stored));
        $this->assertArrayNotHasKey('email_verification_code', $user->fresh()->toArray());
    }

    public function test_a_wrong_code_is_rejected_and_counts_an_attempt(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueEmailVerificationCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->actingAs($user)->post('/verify-email', ['code' => $wrong])
            ->assertSessionHasErrors(['code' => 'Kode salah. Sisa 4 percobaan.']);

        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(1, $user->email_verification_attempts);
    }

    public function test_the_code_is_burned_after_five_wrong_attempts_even_if_the_right_one_follows(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueEmailVerificationCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)->post('/verify-email', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        $this->assertNull($user->fresh()->email_verification_code);

        $this->actingAs($user)->post('/verify-email', ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Kode sudah tidak berlaku. Klik "Kirim Ulang Kode" untuk minta kode baru.']);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueEmailVerificationCode();

        $this->travel(User::EMAIL_CODE_TTL_MINUTES + 1)->minutes();

        $this->actingAs($user)->post('/verify-email', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_code_must_be_six_digits(): void
    {
        $user = User::factory()->unverified()->create();
        $user->issueEmailVerificationCode();

        foreach (['', '12345', '1234567', 'abcdef'] as $input) {
            $this->actingAs($user)->post('/verify-email', ['code' => $input])->assertSessionHasErrors('code');
        }

        // Format salah ditolak di validasi, jadi tidak menghabiskan jatah percobaan.
        $this->assertSame(0, $user->fresh()->email_verification_attempts);
    }

    public function test_resending_sends_a_new_code_and_invalidates_the_old_one(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $old = $user->issueEmailVerificationCode();

        $this->actingAs($user)->post('/email/verification-notification')->assertSessionHas('status', 'verification-code-sent');

        Notification::assertSentTo($user, VerifyEmailCode::class);
        $this->assertFalse(Hash::check($old, $user->fresh()->email_verification_code));
        $this->assertSame(0, $user->fresh()->email_verification_attempts);
    }

    public function test_verifying_requires_login_and_one_user_cannot_verify_another(): void
    {
        $victim = User::factory()->unverified()->create();
        $code = $victim->issueEmailVerificationCode();
        $attacker = User::factory()->unverified()->create();

        $this->post('/verify-email', ['code' => $code])->assertRedirect('/login');

        // Kode milik orang lain tidak bisa dipakai di akun sendiri.
        $this->actingAs($attacker)->post('/verify-email', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($victim->fresh()->hasVerifiedEmail());
        $this->assertFalse($attacker->fresh()->hasVerifiedEmail());
    }

    public function test_an_already_verified_user_is_just_redirected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/verify-email', ['code' => '123456'])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_the_verify_endpoint_is_throttled(): void
    {
        $user = User::factory()->unverified()->create();

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($user)->post('/verify-email', ['code' => 'x']);
        }

        $this->actingAs($user)->post('/verify-email', ['code' => '123456'])->assertStatus(429);
    }
}
