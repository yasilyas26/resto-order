<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function googleReturns(string $id, string $email, string $name = 'Siti Google'): void
    {
        $google = (new SocialiteUser())->map(['id' => $id, 'name' => $name, 'email' => $email]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function googleFails(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('user menolak izin'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_explains_when_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/auth/google')->assertRedirect('/login')->assertSessionHasErrors('email');
    }

    public function test_redirect_sends_the_visitor_to_google_when_configured(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_a_new_google_user_becomes_a_customer_and_is_logged_in(): void
    {
        $this->googleReturns('g-1', 'siti@gmail.test');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'siti@gmail.test')->firstOrFail();
        $this->assertSame(Role::Customer, $user->role);
        $this->assertSame('g-1', $user->google_id);
        $this->assertNull($user->password, 'akun Google tidak punya password');
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_linking_an_unverified_account_wipes_the_squatters_password_and_sessions(): void
    {
        $squatted = User::factory()->unverified()->create(['email' => 'siti@gmail.test']);
        DB::table('sessions')->insert([
            'id' => 'sesi-penyerang', 'user_id' => $squatted->id, 'ip_address' => '1.2.3.4',
            'user_agent' => 'x', 'payload' => '', 'last_activity' => time(),
        ]);
        $this->googleReturns('g-9', 'siti@gmail.test');

        $this->get('/auth/google/callback');

        $squatted->refresh();
        $this->assertNull($squatted->password, 'password buatan pendaftar palsu harus hilang');
        $this->assertSame('g-9', $squatted->google_id);
        $this->assertNotNull($squatted->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-penyerang']);
        $this->assertAuthenticatedAs($squatted);
    }

    public function test_linking_a_verified_account_keeps_its_password(): void
    {
        $existing = User::factory()->create(['email' => 'siti@gmail.test']);
        $hash = $existing->password;
        $this->googleReturns('g-10', 'siti@gmail.test');

        $this->get('/auth/google/callback');

        $this->assertSame($hash, $existing->refresh()->password);
    }

    public function test_an_existing_account_with_the_same_email_is_linked_not_duplicated(): void
    {
        $existing = User::factory()->create(['email' => 'siti@gmail.test', 'name' => 'Siti Lama']);
        $this->googleReturns('g-1', 'siti@gmail.test');

        $this->get('/auth/google/callback');

        $this->assertSame(1, User::where('email', 'siti@gmail.test')->count());
        $this->assertSame('g-1', $existing->fresh()->google_id);
        $this->assertSame('Siti Lama', $existing->fresh()->name, 'data akun lama tidak ditimpa');
        $this->assertAuthenticatedAs($existing);
    }

    public function test_a_returning_google_user_is_found_by_google_id_even_if_the_email_changed(): void
    {
        $user = User::factory()->create(['google_id' => 'g-1', 'email' => 'lama@gmail.test']);
        $this->googleReturns('g-1', 'baru@gmail.test');

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_staff_and_admin_accounts_cannot_be_taken_over_through_google(): void
    {
        foreach ([User::factory()->staff()->create(['email' => 'staff@resto.test']), User::factory()->admin()->create(['email' => 'admin@resto.test'])] as $internal) {
            $this->googleReturns('g-evil', $internal->email);

            $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

            $this->assertGuest();
            $this->assertNull($internal->fresh()->google_id, 'google_id tidak boleh menempel ke akun internal');
        }
    }

    public function test_a_deactivated_account_cannot_log_in_through_google(): void
    {
        $gone = User::factory()->create(['email' => 'pergi@gmail.test']);
        $gone->delete();
        $this->googleReturns('g-9', 'pergi@gmail.test');

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::withTrashed()->where('email', 'pergi@gmail.test')->count());
    }

    public function test_cancelling_at_google_returns_to_login_with_a_message(): void
    {
        $this->googleFails();

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_google_only_account_cannot_log_in_with_a_password(): void
    {
        User::factory()->create(['email' => 'siti@gmail.test', 'password' => null, 'google_id' => 'g-1']);

        $this->post('/login', ['email' => 'siti@gmail.test', 'password' => 'apa-saja-123'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
