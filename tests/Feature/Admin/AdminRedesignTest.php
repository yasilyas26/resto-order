<?php

namespace Tests\Feature\Admin;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Tampilan admin baru: Ringkasan, filter menu, okupansi meja, Riwayat Transaksi, dan Tempat Sampah. */
class AdminRedesignTest extends TestCase
{
    use BuildsMenu;
    use BuildsOrders;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = $this->menuFixture();
        $this->actingAs(User::factory()->admin()->create());
    }

    // ---- Ringkasan ---------------------------------------------------------------

    public function test_dashboard_stats_count_today_revenue_minus_refund_and_average_cook_time(): void
    {
        $done = $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Done, 'paid_at' => now()->subMinutes(30), 'completed_at' => now()->subMinutes(2)]);
        $done->forceFill(['cooking_started_at' => now()->subMinutes(12)])->save();
        $queued = $this->paidOrder($this->f);
        $queued->forceFill(['refund_amount' => 5000])->save();
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        $this->paidOrder($this->f, ['paid_at' => now()->subDay(), 'kitchen_status' => KitchenStatus::Done, 'completed_at' => now()->subDay()]);

        $stats = $this->getJson('/admin/dashboard/data')->assertOk()->json('stats');

        $this->assertSame(43000 * 2 - 5000, $stats['revenue']);
        $this->assertSame(1, $stats['completed']);
        $this->assertEqualsWithDelta(10.0, $stats['avgCookMinutes'], 0.1);
    }

    public function test_revenue_series_defaults_to_14_days_with_zero_days_and_subtracts_refunds(): void
    {
        $today = $this->paidOrder($this->f);
        $today->forceFill(['refund_amount' => 3000])->save();
        $this->paidOrder($this->f, ['paid_at' => now()->subDays(3)]);
        $this->paidOrder($this->f, ['paid_at' => now()->subDays(20)]); // di luar jendela
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);

        $json = $this->getJson('/admin/dashboard/data')->json('stats');
        $series = collect($json['series']);

        $this->assertSame('14d', $json['range']);
        $this->assertSame('day', $json['unit']);
        $this->assertCount(14, $series);
        $this->assertSame(40000, $series->last()['revenue']);
        $this->assertSame(43000, $series[10]['revenue']); // 3 hari lalu
        $this->assertSame(43000 + 40000, $series->sum('revenue'));
    }

    public function test_revenue_series_ranges_group_by_day_week_or_month(): void
    {
        $this->paidOrder($this->f); // hari ini = 43.000
        $this->paidOrder($this->f, ['paid_at' => now()->subDays(40)]);
        $this->paidOrder($this->f, ['paid_at' => now()->subMonths(5)]);

        $expect = ['7d' => [7, 'day'], '14d' => [14, 'day'], '30d' => [30, 'day'], '3m' => [13, 'week'], '6m' => [26, 'week'], '1y' => [12, 'month']];

        foreach ($expect as $range => [$count, $unit]) {
            $stats = $this->getJson("/admin/dashboard/data?range={$range}")->assertOk()->json('stats');

            $this->assertSame($unit, $stats['unit'], $range);
            $this->assertCount($count, $stats['series'], $range);
            $this->assertSame(43000, end($stats['series'])['revenue'], "{$range}: batang terakhir memuat hari ini");
        }

        $sum = fn (string $range) => collect($this->getJson("/admin/dashboard/data?range={$range}")->json('stats.series'))->sum('revenue');
        $this->assertSame(43000, $sum('30d'));
        $this->assertSame(86000, $sum('3m'));
        $this->assertSame(129000, $sum('1y'));
    }

    public function test_an_unknown_range_falls_back_to_the_default(): void
    {
        $this->assertSame('14d', $this->getJson('/admin/dashboard/data?range=bogus')->json('stats.range'));
        $this->assertSame('14d', $this->getJson('/admin/dashboard/data?range[]=7d')->json('stats.range'));
    }

    public function test_dashboard_board_lists_only_paid_orders_and_average_is_empty_without_data(): void
    {
        $this->paidOrder($this->f);
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);

        $json = $this->getJson('/admin/dashboard/data')->json();

        $this->assertCount(1, $json['orders']);
        $this->assertNull($json['stats']['avgCookMinutes']);
        $this->get('/admin')->assertOk()->assertSee('Performa Hari Ini')->assertSee('Pesanan Dapur');
    }

    public function test_starting_an_order_records_when_cooking_began(): void
    {
        $order = $this->paidOrder($this->f);
        $this->actingAs(User::factory()->staff()->create())->postJson("/staff/orders/{$order->code}/start")->assertOk();

        $this->assertNotNull($order->fresh()->cooking_started_at);
    }

    public function test_navigation_has_no_staff_tab(): void
    {
        $this->get('/admin')->assertDontSee('Karyawan</a>', false)->assertSee('Riwayat Transaksi')->assertSee('Tempat Sampah');
    }

    // ---- Manajemen Menu ----------------------------------------------------------

    public function test_menu_list_filters_by_name_category_and_status_and_paginates(): void
    {
        $other = Category::create(['name' => 'Camilan']);
        Menu::create(['category_id' => $other->id, 'name' => 'Kerupuk Pangsit', 'price' => 7000, 'is_available' => false]);

        $this->get('/admin/menus?q=Kerupuk')->assertSee('Kerupuk Pangsit')->assertDontSee('Mie Pedas');
        $this->get('/admin/menus?status=sold_out')->assertSee('Kerupuk Pangsit')->assertDontSee('Mie Pedas');
        $this->get('/admin/menus?status=available')->assertDontSee('Kerupuk Pangsit')->assertSee('Mie Pedas');
        $this->get('/admin/menus?category='.$other->id)->assertSee('Kerupuk Pangsit')->assertDontSee('Mie Pedas');

        foreach (range(1, 12) as $i) {
            Menu::create(['category_id' => $other->id, 'name' => "Menu Banyak {$i}", 'price' => 1000]);
        }
        $this->get('/admin/menus')->assertSee('Menampilkan');
        $this->assertSame(8, count($this->get('/admin/menus?page=1')->viewData('menus')->items()));
    }

    public function test_availability_can_be_toggled_from_the_list(): void
    {
        $menu = $this->f['mie'];
        $this->assertTrue($menu->is_available);

        $this->post("/admin/menus/{$menu->id}/availability")->assertRedirect();
        $this->assertFalse($menu->fresh()->is_available);

        $this->post("/admin/menus/{$menu->id}/availability");
        $this->assertTrue($menu->fresh()->is_available);
    }

    public function test_category_and_option_group_pages_live_as_menu_tabs(): void
    {
        $this->get('/admin/categories')->assertRedirect('/admin/menus?tab=kategori');
        $this->get('/admin/option-groups')->assertRedirect('/admin/menus?tab=opsi');
    }

    public function test_customers_cannot_toggle_availability(): void
    {
        $this->actingAs(User::factory()->create())->post("/admin/menus/{$this->f['mie']->id}/availability")->assertForbidden();
    }

    // ---- Manajemen Meja ----------------------------------------------------------

    public function test_table_stats_count_total_active_and_inactive_tables(): void
    {
        Table::create(['number' => 'F9']);
        Table::create(['number' => 'X1', 'is_active' => false]);

        $stats = $this->get('/admin/tables')->assertOk()->assertDontSee('Status Okupansi Meja')->viewData('stats');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(1, $stats['inactive']);
    }

    public function test_table_search_matches_number_or_code(): void
    {
        Table::create(['number' => 'ZZ7']);

        $this->get('/admin/tables?q=ZZ7')->assertSee('Meja ZZ7');
        $this->assertSame(1, $this->get('/admin/tables?q=ZZ7')->viewData('tables')->total());
    }

    // ---- Riwayat Transaksi -------------------------------------------------------

    public function test_transactions_list_every_order_and_can_be_searched(): void
    {
        $paid = $this->paidOrder($this->f, ['customer_name' => 'Budi Santoso']);
        $pending = $this->paidOrder($this->f, ['customer_name' => 'Citra Lestari', 'payment_status' => PaymentStatus::Pending, 'paid_at' => null]);

        $this->get('/admin/transactions')->assertOk()->assertSee($paid->code)->assertSee($pending->code)->assertSee('Lunas')->assertSee('Menunggu');
        $this->get('/admin/transactions?q=Budi')->assertSee($paid->code)->assertDontSee($pending->code);
        $this->get('/admin/transactions?q='.$pending->code)->assertSee($pending->code)->assertDontSee($paid->code);
    }

    public function test_transactions_are_closed_to_staff_and_customers(): void
    {
        auth()->logout();
        $this->actingAs(User::factory()->staff()->create())->get('/admin/transactions')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/transactions')->assertForbidden();
    }

    // ---- Tempat Sampah -----------------------------------------------------------

    public function test_trash_tabs_filter_by_kind_and_show_retention(): void
    {
        $this->f['mie']->delete();
        Table::create(['number' => 'T9'])->delete();

        $this->get('/admin/trash?tab=meja')->assertSee('Meja T9')->assertDontSee('Mie Pedas');
        $this->get('/admin/trash?tab=menu')->assertSee('Mie Pedas')->assertDontSee('Meja T9');
        $this->get('/admin/trash')->assertSee('Mie Pedas')->assertSee('Meja T9')->assertSee('30 hari tersisa');
    }

    public function test_empty_trash_deletes_everything_removable_and_skips_referenced_items(): void
    {
        $order = $this->paidOrder($this->f); // menu "mie" pernah dipesan
        $this->f['mie']->delete();
        $table = Table::create(['number' => 'T9']);
        $table->delete();

        $this->post('/admin/trash/empty')->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
        $this->assertNotNull(Menu::onlyTrashed()->find($this->f['mie']->id), 'menu yang pernah dipesan tetap di Sampah');
        $this->assertNotNull($order->fresh());
    }

    public function test_purge_command_only_removes_items_older_than_retention(): void
    {
        $old = Table::create(['number' => 'OLD']);
        $old->delete();
        $old->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();
        $recent = Table::create(['number' => 'NEW']);
        $recent->delete();

        $this->artisan('trash:purge')->assertSuccessful();

        $this->assertDatabaseMissing('tables', ['id' => $old->id]);
        $this->assertDatabaseHas('tables', ['id' => $recent->id]);
    }

    public function test_delete_actions_use_the_confirm_modal_not_the_browser_dialog(): void
    {
        $this->f['mie']->delete();
        Table::create(['number' => 'T9'])->delete();
        Table::create(['number' => 'T8']);

        $trash = $this->get('/admin/trash')->assertOk();
        $trash->assertSee('data-confirm-tone="hard"', false)->assertSee('Hapus Permanen', false);
        $this->get('/admin/menus')->assertSee('data-confirm-tone="soft"', false)->assertSee('x-data="confirmDialog"', false);
        $this->get('/admin/tables')->assertSee('data-confirm-title="Pindahkan ke Tempat Sampah?"', false);

        foreach (['/admin/trash', '/admin/menus', '/admin/menus?tab=kategori', '/admin/menus?tab=opsi', '/admin/tables'] as $url) {
            $this->get($url)->assertDontSee('confirm(', false);
        }
    }

    // ---- Menu akun ---------------------------------------------------------------

    public function test_admin_can_change_their_name_from_the_account_menu(): void
    {
        $this->get('/admin')->assertSee('Ganti Nama')->assertSee('Ganti Kata Sandi')->assertSee('Keluar');

        $this->patch('/admin/account/name', ['name' => '  Admin Baru  '])->assertRedirect()->assertSessionHas('name_changed');
        $this->assertSame('Admin Baru', auth()->user()->fresh()->name);

        $this->patch('/admin/account/name', ['name' => ''])->assertSessionHasErrorsIn('adminName', 'name');
    }

    public function test_admin_changes_password_with_current_password_and_confirmation(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('LamaSandi1')]);
        $this->actingAs($admin);

        $this->put('/admin/account/password', ['current_password' => 'salah', 'password' => 'BaruSandi1', 'password_confirmation' => 'BaruSandi1'])
            ->assertSessionHasErrorsIn('adminPassword', 'current_password');
        $this->put('/admin/account/password', ['current_password' => 'LamaSandi1', 'password' => 'BaruSandi1', 'password_confirmation' => 'beda'])
            ->assertSessionHasErrorsIn('adminPassword', 'password');
        $this->put('/admin/account/password', ['current_password' => 'LamaSandi1', 'password' => 'lemah', 'password_confirmation' => 'lemah'])
            ->assertSessionHasErrorsIn('adminPassword', 'password');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('LamaSandi1', $admin->fresh()->password));

        $this->put('/admin/account/password', ['current_password' => 'LamaSandi1', 'password' => 'BaruSandi1', 'password_confirmation' => 'BaruSandi1'])
            ->assertSessionHasNoErrors()->assertSessionHas('password_changed');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('BaruSandi1', $admin->fresh()->password));
    }

    public function test_account_endpoints_are_closed_to_staff_and_customers(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user);
            $this->patch('/admin/account/name', ['name' => 'X'])->assertForbidden();
            $this->put('/admin/account/password', ['password' => 'BaruSandi1', 'password_confirmation' => 'BaruSandi1'])->assertForbidden();
        }
    }

    public function test_purge_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'trash:purge'));

        $this->assertCount(1, $events);
    }

    public function test_loading_skeletons_exist_for_the_chart_and_the_staff_history_list(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Memuat grafik')->assertSee('animate-pulse', false);

        $this->actingAs(User::factory()->staff()->create())->get('/staff/riwayat')->assertOk()
            ->assertSee('Memuat riwayat')->assertSee('animate-pulse', false);
    }

    public function test_every_admin_page_links_to_the_staff_board(): void
    {
        foreach (['/admin', '/admin/menus', '/admin/tables', '/admin/transactions', '/admin/trash'] as $url) {
            $this->get($url)->assertOk()->assertSee('href="'.url('/staff/orders').'"', false)->assertSee('Karyawan');
        }
    }

    public function test_admin_sees_a_success_modal_after_changing_the_name_or_the_password(): void
    {
        $this->withSession(['name_changed' => true])->get('/admin')->assertOk()->assertSee('Nama berhasil diganti')->assertSee('role="alertdialog"', false);
        $this->withSession(['password_changed' => true])->get('/admin')->assertOk()->assertSee('Kata sandi berhasil diganti');

        // Tanpa kabar di sesi, tidak ada modal.
        $this->flushSession();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->assertDontSee('Nama berhasil diganti')->assertDontSee('Kata sandi berhasil diganti');
    }

    public function test_changing_to_the_same_name_shows_a_no_change_modal(): void
    {
        $name = auth()->user()->name;

        $this->patch('/admin/account/name', ['name' => "  {$name}  "])->assertRedirect()->assertSessionMissing('name_changed');

        $this->get('/admin')->assertOk()->assertSee('Tidak ada perubahan')->assertSee('tidak ada yang disimpan');
    }

    public function test_a_failed_name_change_shows_a_failure_modal(): void
    {
        $this->from('/admin')->patch('/admin/account/name', ['name' => ''])->assertRedirect('/admin');

        $this->get('/admin')->assertOk()->assertSee('Nama belum bisa diganti')->assertSee('role="alertdialog"', false);
    }

    public function test_a_failed_password_change_shows_a_failure_modal_and_the_same_password_a_no_change_modal(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('LamaSandi1')]);
        $this->actingAs($admin);

        $this->from('/admin')->put('/admin/account/password', ['current_password' => 'salah', 'password' => 'BaruSandi1', 'password_confirmation' => 'BaruSandi1'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Kata sandi belum bisa diganti');

        $this->flushSession();
        $this->actingAs($admin)->from('/admin')->put('/admin/account/password', ['current_password' => 'LamaSandi1', 'password' => 'LamaSandi1', 'password_confirmation' => 'LamaSandi1'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Tidak ada perubahan')->assertSee('sama dengan yang sekarang')->assertDontSee('Kata sandi berhasil diganti');
    }
}
