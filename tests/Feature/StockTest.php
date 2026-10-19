<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Concerns\BuildsOrders;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/** Stok menu: berkurang setelah dibayar, 0 = otomatis habis, dan tidak bisa dipesan melebihi sisa. */
class StockTest extends TestCase
{
    use BuildsMenu;
    use BuildsOrders;
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test-key';

    /** @var array<string, mixed> */
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => self::KEY]);
        $this->app->instance(PaymentGateway::class, new FakeGateway());
        $this->f = $this->menuFixture();
    }

    /** Order "Mie Pedas" 2x + "Es Teh" 1x yang belum dibayar, lengkap dengan percobaan pembayarannya. */
    private function unpaidOrder(): Order
    {
        $order = $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        $order->payments()->create(['midtrans_order_id' => $order->code.'-1', 'gross_amount' => 43000, 'snap_token' => 't']);

        return $order;
    }

    private function settle(Order $order): void
    {
        $payload = ['order_id' => $order->code.'-1', 'status_code' => '200', 'gross_amount' => '43000.00', 'transaction_status' => 'settlement', 'payment_type' => 'qris'];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::KEY);

        $this->postJson('/payments/midtrans/notification', $payload)->assertOk();
    }

    // ---- berkurang setelah dibayar ----------------------------------------------

    public function test_stock_goes_down_only_after_the_order_is_paid(): void
    {
        $this->f['mie']->update(['stock' => 10]);
        $this->f['esTeh']->update(['stock' => 4]);
        $order = $this->unpaidOrder();

        $this->assertSame(10, $this->f['mie']->fresh()->stock, 'belum dibayar: stok tetap');

        $this->settle($order);

        $this->assertSame(8, $this->f['mie']->fresh()->stock);
        $this->assertSame(3, $this->f['esTeh']->fresh()->stock);
        $this->assertTrue($this->f['mie']->fresh()->is_available);
    }

    public function test_a_repeated_paid_notification_does_not_deduct_stock_twice(): void
    {
        $this->f['mie']->update(['stock' => 10]);
        $order = $this->unpaidOrder();

        $this->settle($order);
        $this->settle($order);
        $this->settle($order);

        $this->assertSame(8, $this->f['mie']->fresh()->stock);
    }

    public function test_reaching_zero_makes_the_menu_unavailable_automatically(): void
    {
        $this->f['mie']->update(['stock' => 2]);
        $this->settle($this->unpaidOrder());

        $mie = $this->f['mie']->fresh();
        $this->assertSame(0, $mie->stock);
        $this->assertFalse($mie->is_available);
    }

    public function test_stock_never_goes_below_zero_when_more_was_paid_than_is_left(): void
    {
        // Dua pelanggan sama-sama sempat membayar saat stok tinggal 1.
        $this->f['mie']->update(['stock' => 1]);
        $this->settle($this->unpaidOrder());

        $this->assertSame(0, $this->f['mie']->fresh()->stock);
    }

    public function test_menus_without_a_stock_limit_are_untouched_and_never_sell_out(): void
    {
        $this->assertNull($this->f['mie']->fresh()->stock);

        $this->settle($this->unpaidOrder());

        $mie = $this->f['mie']->fresh();
        $this->assertNull($mie->stock);
        $this->assertTrue($mie->is_available);
    }

    public function test_unpaid_failed_or_expired_orders_never_touch_the_stock(): void
    {
        $this->f['mie']->update(['stock' => 10]);
        $order = $this->unpaidOrder();

        foreach (['expire', 'cancel', 'deny'] as $status) {
            $payload = ['order_id' => $order->code.'-1', 'status_code' => '202', 'gross_amount' => '43000.00', 'transaction_status' => $status, 'payment_type' => 'qris'];
            $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::KEY);
            $this->postJson('/payments/midtrans/notification', $payload)->assertOk();
        }

        $this->assertSame(10, $this->f['mie']->fresh()->stock);
    }

    // ---- tidak bisa dipesan melebihi sisa ----------------------------------------

    private function checkout(int $qty, array $extra = [])
    {
        return $this->withSession(['table_id' => $this->f['table']->id])->postJson('/checkout', [
            'order_type' => 'dine_in',
            'items' => [['menu_id' => $this->f['mie']->id, 'qty' => $qty, 'options' => [$this->f['lv3']->id]]] + $extra,
        ]);
    }

    public function test_an_order_cannot_ask_for_more_than_the_remaining_stock(): void
    {
        $this->f['mie']->update(['stock' => 3]);

        $this->checkout(4)->assertStatus(422)->assertJsonPath('errors.items.0', 'Stok Mie Pedas tersisa 3. Kurangi jumlahnya untuk melanjutkan.');
        $this->assertDatabaseCount('orders', 0);

        $this->checkout(3)->assertCreated();
    }

    public function test_the_same_menu_on_two_lines_is_counted_together_against_the_stock(): void
    {
        $this->f['mie']->update(['stock' => 3]);

        $this->withSession(['table_id' => $this->f['table']->id])->postJson('/checkout', [
            'order_type' => 'dine_in',
            'items' => [
                ['menu_id' => $this->f['mie']->id, 'qty' => 2, 'options' => [$this->f['lv3']->id]],
                ['menu_id' => $this->f['mie']->id, 'qty' => 2, 'options' => [$this->f['lv3']->id, $this->f['telur']->id]],
            ],
        ])->assertStatus(422);
    }

    public function test_a_menu_that_sold_out_cannot_be_ordered(): void
    {
        $this->f['mie']->update(['stock' => 0, 'is_available' => false]);

        $this->checkout(1)->assertStatus(422);
    }

    // ---- admin -------------------------------------------------------------------

    private function admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->f['mie']->category_id, 'name' => 'Mie Pedas', 'price' => 15000, 'is_available' => 1,
        ], $overrides);
    }

    public function test_admin_sets_a_stock_number_or_clears_it_to_remove_the_limit(): void
    {
        $this->admin();

        $this->put("/admin/menus/{$this->f['mie']->id}", $this->payload(['stock' => 25]))->assertRedirect();
        $this->assertSame(25, $this->f['mie']->fresh()->stock);

        $this->put("/admin/menus/{$this->f['mie']->id}", $this->payload(['stock' => '']))->assertRedirect();
        $this->assertNull($this->f['mie']->fresh()->stock);

        $this->put("/admin/menus/{$this->f['mie']->id}", $this->payload(['stock' => -1]))->assertSessionHasErrors('stock');
        $this->put("/admin/menus/{$this->f['mie']->id}", $this->payload(['stock' => 'abc']))->assertSessionHasErrors('stock');
    }

    public function test_setting_stock_to_zero_makes_the_menu_unavailable_and_refilling_makes_it_available_again(): void
    {
        $this->admin();

        $this->put("/admin/menus/{$this->f['mie']->id}", $this->payload(['stock' => 0]))->assertRedirect();
        $this->assertFalse($this->f['mie']->fresh()->is_available);

        $this->put("/admin/menus/{$this->f['mie']->id}", $this->payload(['stock' => 12, 'is_available' => 0]))->assertRedirect();
        $this->assertTrue($this->f['mie']->fresh()->is_available, 'diisi lagi dari 0: tersedia kembali');
    }

    public function test_a_menu_the_admin_switched_off_by_hand_stays_off_when_the_stock_is_edited(): void
    {
        $this->admin();
        $this->f['mie']->update(['stock' => 5, 'is_available' => false]);

        $this->put("/admin/menus/{$this->f['mie']->id}", $this->payload(['stock' => 8, 'is_available' => 0]))->assertRedirect();

        $this->assertFalse($this->f['mie']->fresh()->is_available);
    }

    public function test_a_new_menu_with_zero_stock_starts_unavailable(): void
    {
        $this->admin();

        $this->post('/admin/menus', $this->payload(['name' => 'Menu Baru', 'stock' => 0]))->assertRedirect();

        $this->assertFalse(Menu::where('name', 'Menu Baru')->firstOrFail()->is_available);
    }

    public function test_the_availability_switch_refuses_to_turn_on_a_menu_with_zero_stock(): void
    {
        $this->admin();
        $this->f['mie']->update(['stock' => 0, 'is_available' => false]);

        $this->post("/admin/menus/{$this->f['mie']->id}/availability")->assertRedirect()->assertSessionHas('error');
        $this->assertFalse($this->f['mie']->fresh()->is_available);

        // Menu berstok yang dimatikan manual boleh dinyalakan lagi.
        $this->f['mie']->update(['stock' => 4]);
        $this->post("/admin/menus/{$this->f['mie']->id}/availability")->assertRedirect();
        $this->assertTrue($this->f['mie']->fresh()->is_available);
    }

    public function test_the_admin_list_shows_stock_and_flags_low_stock(): void
    {
        $this->admin();
        $this->f['mie']->update(['stock' => 3]);

        $this->get('/admin/menus')->assertOk()->assertSee('menipis')->assertSee('Stok');
        $this->get("/admin/menus/{$this->f['mie']->id}/edit")->assertOk()->assertSee('name="stock"', false)->assertSee('Tidak dibatasi', false);
    }

    // ---- pelanggan ---------------------------------------------------------------

    public function test_the_menu_page_shows_remaining_stock_only_when_it_is_low(): void
    {
        $session = ['table_id' => $this->f['table']->id];

        $this->f['mie']->update(['stock' => 3]);
        $this->withSession($session)->get('/menu')->assertOk()->assertSee('Sisa 3');

        $this->f['mie']->update(['stock' => 50]);
        $this->withSession($session)->get('/menu')->assertOk()->assertDontSee('Sisa 50')->assertDontSee('Sisa ');

        $this->f['mie']->update(['stock' => 0, 'is_available' => false]);
        $this->withSession($session)->get('/menu')->assertOk()->assertSee('Habis');
    }

    public function test_the_catalog_sent_to_the_browser_carries_the_stock_for_the_quantity_cap(): void
    {
        $this->f['mie']->update(['stock' => 7]);

        $catalog = collect(\App\Support\MenuCatalog::forScript(\App\Support\MenuCatalog::categories()));
        $mie = $catalog->firstWhere('id', $this->f['mie']->id);

        $this->assertSame(7, $mie['stock']);
        $this->assertNull($catalog->firstWhere('id', $this->f['esTeh']->id)['stock'], 'tanpa batas = null');
    }
}
