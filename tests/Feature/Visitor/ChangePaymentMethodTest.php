<?php

namespace Tests\Feature\Visitor;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/** Ganti metode bayar: transaksi lama dibatalkan dan diganti percobaan baru, tanpa risiko terbayar dua kali. */
class ChangePaymentMethodTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test-key';

    private FakeGateway $gateway;

    private Order $order;

    private Payment $first;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => self::KEY]);
        $this->gateway = new FakeGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);

        $f = $this->menuFixture();
        $this->order = Order::create([
            'code' => 'ORD-ABCDEF', 'table_id' => $f['table']->id, 'customer_name' => 'Meja A07',
            'order_type' => 'dine_in', 'subtotal' => 9500, 'total' => 9500, 'expires_at' => now()->addMinutes(15),
        ]);
        $this->first = $this->order->payments()->create([
            'midtrans_order_id' => 'ORD-ABCDEF-1', 'gross_amount' => 9500, 'snap_token' => 'token-lama',
        ]);
        $this->withSession(['my_orders' => ['ORD-ABCDEF']]); // browser ini pemilik order
    }

    private function change()
    {
        return $this->postJson('/order/ORD-ABCDEF/ganti-metode');
    }

    private function midtransSays(string $status, string $type = 'qris'): void
    {
        $this->gateway->statuses['ORD-ABCDEF-1'] = [
            'order_id' => 'ORD-ABCDEF-1', 'status_code' => '201', 'gross_amount' => '9500.00',
            'transaction_status' => $status, 'payment_type' => $type,
        ];
    }

    public function test_a_pending_qris_attempt_is_cancelled_and_replaced_by_a_new_one(): void
    {
        $this->midtransSays('pending');

        $response = $this->change()->assertOk();

        $this->assertSame(['ORD-ABCDEF-1'], $this->gateway->cancelled);
        $this->assertSame('cancel', $this->first->fresh()->transaction_status);

        $second = $this->order->payments()->where('midtrans_order_id', 'ORD-ABCDEF-2')->firstOrFail();
        $response->assertJson(['snap_token' => $second->snap_token]);
        $this->assertNotSame('token-lama', $second->snap_token);
        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_if_the_old_attempt_was_actually_paid_the_order_is_settled_and_no_new_attempt_is_made(): void
    {
        $this->midtransSays('settlement');

        $this->change()->assertStatus(409)->assertJsonFragment(['message' => 'Pembayaranmu sudah kami terima. Pesanan ini tidak perlu dibayar lagi.']);

        $this->assertSame(PaymentStatus::Paid, $this->order->fresh()->payment_status);
        $this->assertSame([], $this->gateway->cancelled, 'tidak ada yang dibatalkan');
        $this->assertSame(1, $this->order->payments()->count(), 'tidak ada percobaan baru, jadi tidak bisa terbayar dua kali');
        $this->assertNotNull($this->order->fresh()->queueLabel());
    }

    public function test_when_midtrans_refuses_to_cancel_nothing_changes(): void
    {
        $this->midtransSays('pending');
        $this->gateway->failCancel = true;

        $this->change()->assertStatus(502);

        $this->assertSame(1, $this->order->payments()->count(), 'tidak membuat percobaan baru kalau yang lama belum pasti batal');
        $this->assertSame('pending', $this->first->fresh()->transaction_status);
    }

    public function test_when_midtrans_cannot_be_reached_nothing_changes(): void
    {
        $this->gateway->failStatus = true;

        $this->change()->assertStatus(502);

        $this->assertSame(1, $this->order->payments()->count());
        $this->assertSame([], $this->gateway->cancelled);
    }

    public function test_before_any_method_is_chosen_the_same_token_is_simply_reused(): void
    {
        // Midtrans belum mengenal transaksinya: pelanggan belum memilih metode, jadi tidak ada yang perlu dibatalkan.
        $this->change()->assertOk()->assertJson(['snap_token' => 'token-lama']);

        $this->assertSame([], $this->gateway->cancelled);
        $this->assertSame(1, $this->order->payments()->count());
    }

    public function test_a_paid_order_cannot_change_method(): void
    {
        $this->order->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        $this->change()->assertStatus(409);

        $this->assertSame(1, $this->order->payments()->count());
    }

    public function test_a_late_cancel_notification_for_the_old_attempt_does_not_fail_the_order(): void
    {
        $this->midtransSays('pending');
        $this->change()->assertOk();

        $payload = [
            'order_id' => 'ORD-ABCDEF-1', 'status_code' => '200', 'gross_amount' => '9500.00',
            'transaction_status' => 'cancel', 'payment_type' => 'qris',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].'200'.$payload['gross_amount'].self::KEY);

        $this->postJson('/payments/midtrans/notification', $payload)->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status, 'percobaan baru masih berjalan');
    }

    public function test_the_status_page_offers_the_change_button(): void
    {
        $this->get('/order/ORD-ABCDEF')->assertOk()->assertSee('Ganti metode bayar');
    }
}
