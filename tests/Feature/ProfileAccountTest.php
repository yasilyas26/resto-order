<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** Halaman profil pelanggan: nama, kata sandi, dan hapus akun (permanen, dikonfirmasi kata sandi atau login ulang Google). */
class ProfileAccountTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(User $user): Order
    {
        $table = Table::create(['number' => 'A07']);

        return Order::create([
            'code' => 'ORD-AAAAAA', 'table_id' => $table->id, 'user_id' => $user->id, 'customer_name' => $user->name,
            'customer_contact' => 'rahasia@kontak.test', 'order_type' => 'dine_in', 'subtotal' => 10000, 'total' => 10000,
        ]);
    }

    private function googleUser(): User
    {
        return User::factory()->create(['password' => null, 'google_id' => 'g-1']);
    }

    private function googleReturns(string $id): void
    {
        $google = (new SocialiteUser())->map(['id' => $id, 'name' => 'Siti', 'email' => 'siti@gmail.test']);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $provider->shouldReceive('user')->andReturn($google);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_profile_page_shows_name_password_and_a_single_delete_section(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')
            ->assertOk()->assertSee('Nama pengguna')->assertSee('Konfirmasi kata sandi baru')
            ->assertSee('Hapus akun')->assertSee('Masukkan kata sandimu untuk konfirmasi')
            ->assertDontSee('Nonaktifkan');
    }

    public function test_name_can_be_changed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'Nama Baru', 'email' => $user->email])->assertRedirect('/profile');

        $this->assertSame('Nama Baru', $user->fresh()->name);
    }

    public function test_password_change_needs_the_current_password_and_a_matching_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->from('/profile')->put('/password', ['current_password' => 'salah', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->from('/profile')->put('/password', ['current_password' => 'password', 'password' => 'Baru12345', 'password_confirmation' => 'Beda12345'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->from('/profile')->put('/password', ['current_password' => 'password', 'password' => 'lemah', 'password_confirmation' => 'lemah'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->put('/password', ['current_password' => 'password', 'password' => 'Baru12345xyz', 'password_confirmation' => 'Baru12345xyz'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Baru12345xyz', $user->fresh()->password));
    }

    public function test_a_google_account_can_create_a_password_without_a_current_one(): void
    {
        $user = $this->googleUser();

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Buat kata sandi')->assertDontSee('Kata sandi saat ini');

        $this->put('/password', ['password' => 'Baru12345xyz', 'password_confirmation' => 'Baru12345xyz'])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasPassword());
    }

    // ---- hapus akun -------------------------------------------------------------

    public function test_deleting_removes_the_user_but_keeps_the_order_anonymised(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $order->refresh();
        $this->assertNull($order->user_id);
        $this->assertSame('Pelanggan terhapus', $order->customer_name);
        $this->assertNull($order->customer_contact);
        $this->assertSame(10000, $order->total, 'catatan keuangan tetap utuh');
    }

    public function test_wrong_or_missing_password_deletes_nothing(): void
    {
        $user = User::factory()->create();

        foreach (['salah', ''] as $password) {
            $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => $password])
                ->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'password');
        }

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_the_soft_delete_route_is_gone(): void
    {
        $this->actingAs(User::factory()->create())->delete('/profile/permanen', ['password' => 'password'])->assertNotFound();
    }

    public function test_a_google_account_without_a_password_cannot_delete_with_the_form(): void
    {
        $user = $this->googleUser();

        $this->actingAs($user)->from('/profile')->delete('/profile', ['confirmation' => 'HAPUS', 'password' => 'apa saja'])
            ->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'google');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_google_account_confirms_by_signing_in_to_google_again(): void
    {
        $user = $this->googleUser();
        $order = $this->orderFor($user);
        $this->googleReturns('g-1');
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $this->actingAs($user)->get('/auth/google/konfirmasi-hapus')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame('Pelanggan terhapus', $order->fresh()->customer_name);
    }

    public function test_choosing_a_different_google_account_deletes_nothing(): void
    {
        $user = $this->googleUser();
        $this->googleReturns('g-LAIN');
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $this->actingAs($user)->get('/auth/google/konfirmasi-hapus');
        $this->get('/auth/google/callback')->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'google');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_the_google_callback_alone_never_deletes_a_logged_in_user(): void
    {
        $user = $this->googleUser();
        $this->googleReturns('g-1');

        // Tanpa lewat tombol konfirmasi (tidak ada tanda di sesi): tidak terjadi apa-apa.
        $this->actingAs($user)->get('/auth/google/callback')->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_password_accounts_cannot_use_the_google_confirmation(): void
    {
        $this->actingAs(User::factory()->create())->get('/auth/google/konfirmasi-hapus')->assertForbidden();
    }

    public function test_staff_and_admin_cannot_delete_their_own_account(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertForbidden();
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->get('/profile')->assertOk()->assertDontSee('Hapus akun');
        }
    }
}
