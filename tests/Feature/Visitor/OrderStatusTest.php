<?php

namespace Tests\Feature\Visitor;

use App\Enums\KitchenStatus;
use App\Enums\OrderStage;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test-key';

    private FakeGateway $gateway;

    /** @var array<string, mixed> */
    private array $f;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => self::KEY]);
        $this->gateway = new FakeGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->f = $this->menuFixture();

        $this->order = Order::create([
            'code' => 'ORD-ABCDEF', 'table_id' => $this->f['table']->id,
            'customer_name' => 'Budi Rahasia', 'customer_contact' => 'budi@rahasia.test',
            'order_type' => 'dine_in', 'subtotal' => 38000, 'total' => 38000, 'expires_at' => now()->addMinutes(15),
        ]);
        $item = $this->order->items()->create([
            'menu_id' => $this->f['mie']->id, 'menu_name' => 'Mie Pedas', 'unit_price' => 15000,
            'qty' => 2, 'note' => 'tanpa bawang', 'line_total' => 38000,
        ]);
        $item->options()->create(['group_name' => 'Level pedas', 'option_name' => 'Level 3', 'extra_price' => 1000]);
        $item->options()->create(['group_name' => 'Topping', 'option_name' => 'Telur', 'extra_price' => 3000]);
        $this->order->payments()->create(['midtrans_order_id' => 'ORD-ABCDEF-1', 'gross_amount' => 38000, 'snap_token' => 'tok']);
    }

    private function setStatus(PaymentStatus $payment, KitchenStatus $kitchen = KitchenStatus::Queued): void
    {
        $this->order->forceFill(['payment_status' => $payment, 'kitchen_status' => $kitchen])->save();
    }

    public function test_status_page_shows_items_options_note_and_total(): void
    {
        $this->get('/order/ORD-ABCDEF')->assertOk()
            ->assertSee('ORD-ABCDEF')
            ->assertSee('Mie Pedas')
            ->assertSee('2x')
            ->assertSee('Level 3')
            ->assertSee('Telur')
            ->assertSee('tanpa bawang')
            ->assertSee('Rp 38.000')
            ->assertSee('Meja A07')
            ->assertSee('Menunggu Pembayaran');
    }

    public function test_status_page_never_reveals_who_the_customer_is(): void
    {
        $html = $this->get('/order/ORD-ABCDEF')->assertOk()->getContent();

        $this->assertStringNotContainsString('Budi Rahasia', $html);
        $this->assertStringNotContainsString('budi@rahasia.test', $html);

        $json = $this->getJson('/order/ORD-ABCDEF/status')->assertOk()->getContent();
        $this->assertStringNotContainsString('Budi', $json);
        $this->assertStringNotContainsString('rahasia', $json);
    }

    public function test_unknown_order_codes_are_404(): void
    {
        $this->get('/order/ORD-ZZZZZZ')->assertNotFound();
        $this->get('/order/1')->assertNotFound();
        $this->getJson('/order/ORD-ZZZZZZ/status')->assertNotFound();
    }

    public function test_polling_endpoint_follows_the_order_through_every_stage(): void
    {
        $stage = fn () => $this->getJson('/order/ORD-ABCDEF/status')->assertOk();

        $stage()->assertJson(['stage' => 'waiting', 'step' => 1, 'paid' => false, 'payable' => true]);

        $this->setStatus(PaymentStatus::Paid, KitchenStatus::Queued);
        $stage()->assertJson(['stage' => 'paid', 'step' => 2, 'paid' => true, 'payable' => false, 'label' => 'Dibayar']);

        $this->setStatus(PaymentStatus::Paid, KitchenStatus::Cooking);
        $stage()->assertJson(['stage' => 'cooking', 'step' => 3, 'label' => 'Sedang Dibuat']);

        $this->setStatus(PaymentStatus::Paid, KitchenStatus::Done);
        $stage()->assertJson(['stage' => 'done', 'step' => 4, 'label' => 'Selesai']);
    }

    public function test_expired_and_failed_orders_are_outside_the_normal_flow_only_failed_can_be_paid_again(): void
    {
        $this->setStatus(PaymentStatus::Expired);
        $this->getJson('/order/ORD-ABCDEF/status')->assertJson(['stage' => 'expired', 'label' => 'Dibatalkan', 'step' => 0, 'payable' => false]);

        $this->setStatus(PaymentStatus::Failed);
        $this->getJson('/order/ORD-ABCDEF/status')->assertJson(['stage' => 'failed', 'step' => 0, 'payable' => true]);
    }

    public function test_a_pending_order_past_its_deadline_is_shown_as_expired(): void
    {
        $this->order->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(OrderStage::Expired, $this->order->fresh()->stage());
        $this->getJson('/order/ORD-ABCDEF/status')->assertJson(['stage' => 'expired']);
        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status, 'tampilan saja, tidak mengubah database');
    }

    public function test_a_paid_order_stays_paid_regardless_of_the_deadline(): void
    {
        $this->setStatus(PaymentStatus::Paid);
        $this->order->update(['expires_at' => now()->subHour()]);

        $this->assertSame(OrderStage::Paid, $this->order->fresh()->stage());
    }

    public function test_refund_info_is_exposed_for_the_status_page(): void
    {
        $this->setStatus(PaymentStatus::Paid);
        $this->order->forceFill(['refund_status' => RefundStatus::Needed, 'refund_amount' => 15000])->save();

        $this->getJson('/order/ORD-ABCDEF/status')->assertJson(['refund' => 'needed', 'refundAmount' => 15000]);
        $this->get('/order/ORD-ABCDEF')->assertSee('Ada item yang tidak tersedia');
    }

    // ---- Cek ulang pembayaran (cadangan kalau webhook telat) -------------------------------

    public function test_recheck_asks_midtrans_and_marks_the_order_paid_when_midtrans_says_so(): void
    {
        $this->gateway->statuses['ORD-ABCDEF-1'] = [
            'order_id' => 'ORD-ABCDEF-1', 'status_code' => '200', 'gross_amount' => '38000.00',
            'transaction_status' => 'settlement', 'payment_type' => 'gopay', 'transaction_time' => '2026-10-18 14:05:00',
        ];

        $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')
            ->assertOk()
            ->assertJson(['stage' => 'paid', 'paid' => true, 'paymentLabel' => 'GoPay']);

        $this->assertSame(PaymentStatus::Paid, $this->order->fresh()->payment_status);
    }

    public function test_recheck_changes_nothing_when_midtrans_has_no_such_transaction_yet(): void
    {
        $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')->assertOk()->assertJson(['stage' => 'waiting', 'paid' => false]);

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_recheck_still_refuses_an_amount_that_does_not_match(): void
    {
        $this->gateway->statuses['ORD-ABCDEF-1'] = [
            'order_id' => 'ORD-ABCDEF-1', 'status_code' => '200', 'gross_amount' => '100.00', 'transaction_status' => 'settlement',
        ];

        $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')->assertOk()->assertJson(['paid' => false]);

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_recheck_survives_a_midtrans_outage_with_a_friendly_message(): void
    {
        $this->gateway->failStatus = true;

        $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')
            ->assertOk()
            ->assertJson(['stage' => 'waiting', 'message' => 'Belum bisa menghubungi Midtrans. Coba lagi sebentar lagi.']);
    }

    public function test_recheck_works_even_when_midtrans_is_not_configured(): void
    {
        $this->gateway->configured = false;

        $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')->assertOk()->assertJson(['stage' => 'waiting']);
    }

    public function test_recheck_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')->assertOk();
        }

        $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')->assertStatus(429);
    }

    public function test_order_more_button_goes_to_the_menu_only_with_an_active_table(): void
    {
        $this->get('/order/ORD-ABCDEF')->assertSee('href="'.url('/').'"', false);

        $this->withSession(['table_id' => $this->f['table']->id])->get('/order/ORD-ABCDEF')
            ->assertSee('href="'.url('/menu').'"', false);
    }

    public function test_midtrans_finish_redirect_returns_the_customer_to_their_order_page(): void
    {
        $this->get('/order/selesai?order_id=ORD-ABCDEF-1&status_code=200&transaction_status=settlement')
            ->assertRedirect(route('order.result', $this->order));
    }

    public function test_finish_redirect_with_unknown_or_missing_order_id_goes_home(): void
    {
        $this->get('/order/selesai?order_id=ORD-TIDAKADA-1')->assertRedirect(route('home'));
        $this->get('/order/selesai')->assertRedirect(route('home'));
    }
}
