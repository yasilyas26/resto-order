<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLoginTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $tab, string $password = 'password')
    {
        return $this->post('/portal', ['email' => $user->email, 'password' => $password, 'tab' => $tab]);
    }

    public function test_portal_page_shows_both_role_tabs_and_a_way_to_the_customer_side(): void
    {
        $this->get('/portal')->assertOk()
            ->assertSee('Masuk Portal Restoran')
            ->assertSee('Karyawan')->assertSee('Admin')
            ->assertSee('Masuk ke Sistem Operasional')
            ->assertSee('Hubungi Supervisor')
            ->assertSee('Menuju Pemesanan Pelanggan')
            ->assertDontSee('Cabang', false);
    }

    public function test_staff_logs_in_on_the_staff_tab_and_lands_on_the_board(): void
    {
        $staff = User::factory()->staff()->create();

        $this->login($staff, 'staff')->assertRedirect('/staff/orders');

        $this->assertAuthenticatedAs($staff);
    }

    public function test_admin_logs_in_on_the_admin_tab_and_lands_on_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->login($admin, 'admin')->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_an_account_on_the_wrong_tab_is_rejected_and_not_left_logged_in(): void
    {
        $this->login(User::factory()->staff()->create(), 'admin')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->login(User::factory()->admin()->create(), 'staff')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_customers_cannot_use_the_portal(): void
    {
        $customer = User::factory()->create();

        $this->login($customer, 'staff')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->login($customer, 'admin')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_rejected_role_looks_exactly_like_a_wrong_password(): void
    {
        $customer = User::factory()->create();

        $wrongRole = $this->login($customer, 'staff');
        $wrongPassword = $this->login(User::factory()->staff()->create(), 'staff', 'salah-semua');

        $this->assertSame(
            $wrongRole->getSession()->get('errors')->get('email'),
            $wrongPassword->getSession()->get('errors')->get('email'),
            'pesan sama, supaya orang luar tidak bisa menebak akun mana yang ada',
        );
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->login(User::factory()->staff()->create(), 'staff', 'salah')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failures_lock_the_login_temporarily(): void
    {
        $staff = User::factory()->staff()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->login($staff, 'staff', 'salah')->assertSessionHasErrors('email');
        }

        $this->login($staff, 'staff', 'password')->assertSessionHasErrors('email');
        $this->assertGuest(); // walau passwordnya benar, akun sedang dikunci
    }

    public function test_role_mismatches_also_count_toward_the_lockout(): void
    {
        $customer = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->login($customer, 'staff')->assertSessionHasErrors('email');
        }

        $staffTab = $this->login($customer, 'staff');
        $staffTab->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak', $staffTab->getSession()->get('errors')->first('email'));
    }

    public function test_a_logged_in_staff_member_is_sent_straight_to_the_board(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get('/portal')->assertRedirect('/staff/orders');
    }

    public function test_logging_out_returns_to_the_portal(): void
    {
        $this->actingAs(User::factory()->staff()->create())->post('/portal/logout')->assertRedirect('/portal');

        $this->assertGuest();
        $this->get('/staff/orders')->assertRedirect('/login');
    }

    public function test_logout_requires_being_logged_in(): void
    {
        $this->post('/portal/logout')->assertRedirect('/login');
    }

    public function test_the_regular_login_page_still_works_for_staff_too(): void
    {
        $staff = User::factory()->staff()->create();

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/staff/orders');
    }

    public function test_the_portal_is_never_linked_from_customer_pages(): void
    {
        foreach (['/', '/login', '/register', '/riwayat'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('/portal', false);
        }
    }
}
