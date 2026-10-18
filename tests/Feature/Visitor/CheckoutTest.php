<?php

namespace Tests\Feature\Visitor;

use App\Enums\KitchenStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private FakeGateway $gateway;

    /** @var array<string, mixed> */
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->f = $this->menuFixture();
    }

    private function checkout(array $overrides = [], bool $withTable = true)
    {
        $payload = array_merge([
            'customer_name' => 'Budi Santoso',
            'customer_contact' => null,
            'order_type' => 'dine_in',
            'items' => [
                ['menu_id' => $this->f['mie']->id, 'qty' => 2, 'note' => 'tanpa bawang', 'options' => [$this->f['lv3']->id, $this->f['telur']->id]],
                ['menu_id' => $this->f['esTeh']->id, 'qty' => 1, 'options' => []],
            ],
        ], $overrides);

        $client = $withTable ? $this->withSession(['table_id' => $this->f['table']->id]) : $this;

        return $client->postJson('/checkout', $payload);
    }

    public function test_checkout_creates_a_pending_order_with_items_and_options(): void
    {
        $response = $this->checkout()->assertCreated();

        $order = Order::firstOrFail();
        $response->assertJson([
            'order_code' => $order->code,
            'order_url' => url('/order/'.$order->code),
            'snap_token' => 'snap-token-1-'.$order->code.'-1',
        ]);

        $this->assertMatchesRegularExpression('/^ORD-[A-HJ-NP-Z2-9]{6}$/', $order->code);
        $this->assertSame($this->f['table']->id, $order->table_id);
        $this->assertSame('Budi Santoso', $order->customer_name);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(KitchenStatus::Queued, $order->kitchen_status);
        $this->assertSame(OrderType::DineIn, $order->order_type);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->user_id);

        $this->assertCount(2, $order->items);
        $mie = $order->items->firstWhere('menu_name', 'Mie Pedas');
        $this->assertSame(2, $mie->qty);
        $this->assertSame('tanpa bawang', $mie->note);
        $this->assertSame(['Level 3', 'Telur'], $mie->options->pluck('option_name')->all());
        $this->assertSame(['Level pedas', 'Topping'], $mie->options->pluck('group_name')->all());
    }

    public function test_total_is_recomputed_on_the_server(): void
    {
        $this->checkout()->assertCreated();

        // Mie: (15.000 + 1.000 Level 3 + 3.000 Telur) x 2 = 38.000, ditambah Es Teh 5.000 = 43.000
        $order = Order::firstOrFail();
        $this->assertSame(43000, $order->total);
        $this->assertSame(43000, $order->subtotal, 'tidak ada pajak atau biaya layanan terpisah');
        $this->assertSame(38000, $order->items->firstWhere('menu_name', 'Mie Pedas')->line_total);
    }

    public function test_prices_sent_by_the_browser_are_ignored(): void
    {
        $this->checkout([
            'total' => 1,
            'subtotal' => 1,
            'items' => [[
                'menu_id' => $this->f['mie']->id, 'qty' => 1, 'options' => [$this->f['lv0']->id],
                'price' => 1, 'unit_price' => 1, 'line_total' => 1, 'extra_price' => -5000, 'name' => 'Gratis',
            ]],
        ])->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame(15000, $order->total);
        $this->assertSame('Mie Pedas', $order->items->first()->menu_name);
    }

    public function test_the_midtrans_payment_attempt_matches_the_order(): void
    {
        $this->checkout()->assertCreated();

        $order = Order::firstOrFail();
        $payment = Payment::firstOrFail();

        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame($order->code.'-1', $payment->midtrans_order_id);
        $this->assertSame(43000, $payment->gross_amount);
        $this->assertNotNull($payment->snap_token);
        $this->assertNull($payment->transaction_status);

        $this->assertSame(
            ['midtransOrderId' => $order->code.'-1', 'grossAmount' => 43000, 'customerName' => 'Budi Santoso', 'expiryMinutes' => 15],
            $this->gateway->tokenRequests[0],
        );
        $this->assertTrue($order->expires_at->isFuture());
    }

    public function test_item_name_and_price_are_copied_so_later_menu_changes_do_not_alter_history(): void
    {
        $this->checkout()->assertCreated();

        $this->f['mie']->update(['name' => 'Mie Super', 'price' => 99000]);
        $this->f['telur']->update(['extra_price' => 50000]);

        $order = Order::firstOrFail()->load('items.options');
        $mie = $order->items->firstWhere('menu_name', 'Mie Pedas');
        $this->assertSame(15000, $mie->unit_price);
        $this->assertSame(38000, $mie->line_total);
        $this->assertSame(3000, $mie->options->firstWhere('option_name', 'Telur')->extra_price);
        $this->assertSame(43000, $order->total);
    }

    public function test_order_is_linked_to_the_logged_in_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['table_id' => $this->f['table']->id])->postJson('/checkout', [
            'customer_name' => $user->name,
            'order_type' => 'takeaway',
            'items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 1]],
        ])->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(OrderType::Takeaway, $order->order_type);
    }

    // ---- penolakan ------------------------------------------------------------

    public function test_checkout_needs_a_valid_table_in_the_session(): void
    {
        $this->checkout(withTable: false)->assertStatus(409)->assertJsonPath('redirect', url('/'));

        $this->f['table']->update(['is_active' => false]);
        $this->checkout()->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_input_is_validated(): void
    {
        $this->checkout(['customer_name' => ''])->assertJsonValidationErrors('customer_name');
        $this->checkout(['order_type' => 'delivery'])->assertJsonValidationErrors('order_type');
        $this->checkout(['items' => []])->assertJsonValidationErrors('items');
        $this->checkout(['items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 0]]])->assertJsonValidationErrors('items.0.qty');
        $this->checkout(['items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 51]]])->assertJsonValidationErrors('items.0.qty');
        $this->checkout(['items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 1, 'note' => str_repeat('x', 201)]]])
            ->assertJsonValidationErrors('items.0.note');
        $this->checkout(['customer_name' => str_repeat('a', 101)])->assertJsonValidationErrors('customer_name');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_required_option_group_must_be_chosen(): void
    {
        $this->checkout(['items' => [['menu_id' => $this->f['mie']->id, 'qty' => 1, 'options' => []]]])
            ->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'Pilih minimal 1 "Level pedas" untuk Mie Pedas.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_too_many_choices_in_a_group_are_rejected(): void
    {
        $this->checkout(['items' => [['menu_id' => $this->f['mie']->id, 'qty' => 1, 'options' => [$this->f['lv0']->id, $this->f['lv3']->id]]]])
            ->assertStatus(422);
    }

    public function test_an_option_that_belongs_to_another_menu_is_rejected(): void
    {
        $other = $this->f['esTeh']; // Es Teh tidak punya grup opsi sama sekali

        $this->checkout(['items' => [['menu_id' => $other->id, 'qty' => 1, 'options' => [$this->f['telur']->id]]]])
            ->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'Ada pilihan yang tidak valid untuk Es Teh.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unknown_deleted_or_unavailable_things_are_rejected(): void
    {
        $this->checkout(['items' => [['menu_id' => 99999, 'qty' => 1]]])->assertStatus(422);

        $this->f['esTeh']->update(['is_available' => false]);
        $this->checkout(['items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 1]]])
            ->assertStatus(422)->assertJsonPath('errors.items.0', 'Es Teh sedang habis. Hapus dari keranjang untuk melanjutkan.');

        $this->f['esTeh']->update(['is_available' => true]);
        $this->f['esTeh']->delete();
        $this->checkout(['items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 1]]])->assertStatus(422);

        // Opsi habis (Bakso) dan opsi yang sudah dihapus
        $this->checkout(['items' => [['menu_id' => $this->f['mie']->id, 'qty' => 1, 'options' => [$this->f['lv0']->id, $this->f['bakso']->id]]]])
            ->assertStatus(422);
        $this->f['keju']->delete();
        $this->checkout(['items' => [['menu_id' => $this->f['mie']->id, 'qty' => 1, 'options' => [$this->f['lv0']->id, $this->f['keju']->id]]]])
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_all_cart_problems_are_reported_together(): void
    {
        $this->f['esTeh']->update(['is_available' => false]);

        $response = $this->checkout(['items' => [
            ['menu_id' => $this->f['mie']->id, 'qty' => 1, 'options' => []],
            ['menu_id' => $this->f['esTeh']->id, 'qty' => 1],
        ]])->assertStatus(422);

        $this->assertCount(2, $response->json('errors.items'));
    }

    // ---- pembayaran -----------------------------------------------------------

    public function test_checkout_is_refused_before_creating_an_order_when_midtrans_is_not_configured(): void
    {
        $this->gateway->configured = false;

        $this->checkout()->assertStatus(503);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_when_midtrans_fails_the_order_is_kept_and_the_visitor_is_told_where_to_retry(): void
    {
        $this->gateway->failTokens = true;

        $response = $this->checkout()->assertStatus(502);

        $order = Order::firstOrFail();
        $response->assertJsonPath('order_code', $order->code)->assertJsonPath('order_url', url('/order/'.$order->code));
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertNull(Payment::firstOrFail()->snap_token);
    }

    public function test_paying_again_reuses_the_open_token_instead_of_piling_up_transactions(): void
    {
        $this->checkout()->assertCreated();
        $order = Order::firstOrFail();

        $first = $this->postJson("/order/{$order->code}/bayar")->assertOk()->json('snap_token');
        $second = $this->postJson("/order/{$order->code}/bayar")->assertOk()->json('snap_token');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_after_the_attempt_expires_a_new_attempt_gets_a_new_unique_midtrans_id(): void
    {
        $this->checkout()->assertCreated();
        $order = Order::firstOrFail();
        Payment::firstOrFail()->update(['transaction_status' => 'expire']);
        $order->payment_status = PaymentStatus::Expired;
        $order->save();

        $this->postJson("/order/{$order->code}/bayar")->assertOk();

        $this->assertSame([$order->code.'-1', $order->code.'-2'], Payment::orderBy('id')->pluck('midtrans_order_id')->all());
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status, 'order kembali menunggu pembayaran');
    }

    public function test_a_failed_token_request_can_be_retried_from_the_status_page(): void
    {
        $this->gateway->failTokens = true;
        $this->checkout()->assertStatus(502);
        $order = Order::firstOrFail();

        $this->gateway->failTokens = false;
        $this->postJson("/order/{$order->code}/bayar")->assertOk()->assertJsonStructure(['snap_token']);

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($order->code.'-1', Payment::firstOrFail()->midtrans_order_id);
    }

    public function test_a_paid_order_cannot_be_paid_again(): void
    {
        $this->checkout()->assertCreated();
        $order = Order::firstOrFail();
        $order->payment_status = PaymentStatus::Paid;
        $order->save();

        $this->postJson("/order/{$order->code}/bayar")->assertStatus(409);
    }

    public function test_checkout_has_a_per_session_and_a_looser_per_ip_limit(): void
    {
        // Di tes, tiap request mendapat session baru, jadi aturannya diperiksa langsung.
        $request = \Illuminate\Http\Request::create('/checkout', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $limits = \Illuminate\Support\Facades\RateLimiter::limiter('checkout')($request);

        $this->assertCount(2, $limits);
        $this->assertSame(10, $limits[0]->maxAttempts);
        $this->assertStringStartsWith('sesi:', $limits[0]->key);
        $this->assertSame(40, $limits[1]->maxAttempts, 'lebih longgar: beberapa orang bisa berbagi Wi-Fi yang sama');
        $this->assertStringStartsWith('ip:', $limits[1]->key);
    }

    public function test_one_ip_hammering_checkout_is_eventually_blocked(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->checkout(['customer_name' => ''])->assertStatus(422);
        }

        $this->checkout(['customer_name' => ''])->assertStatus(429);
        $this->assertDatabaseCount('orders', 0);
    }
}
