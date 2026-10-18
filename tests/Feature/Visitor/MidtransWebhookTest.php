<?php

namespace Tests\Feature\Visitor;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\TestCase;

/**
 * Status lunas hanya boleh datang dari notifikasi Midtrans yang signature-nya terverifikasi.
 */
class MidtransWebhookTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test-key';

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => self::KEY]);

        $f = $this->menuFixture();
        $this->order = Order::create([
            'code' => 'ORD-ABCDEF', 'table_id' => $f['table']->id, 'customer_name' => 'Budi',
            'order_type' => 'dine_in', 'subtotal' => 43000, 'total' => 43000, 'expires_at' => now()->addMinutes(15),
        ]);
        $this->payment = $this->order->payments()->create([
            'midtrans_order_id' => 'ORD-ABCDEF-1', 'gross_amount' => 43000, 'snap_token' => 'tok',
        ]);
    }

    /** Notifikasi seperti yang dikirim Midtrans, lengkap dengan signature_key yang benar. */
    private function payload(string $status, array $extra = [], ?Payment $payment = null, string $amount = '43000.00'): array
    {
        $payment ??= $this->payment;
        $statusCode = '200';
        $payload = array_merge([
            'order_id' => $payment->midtrans_order_id,
            'status_code' => $statusCode,
            'gross_amount' => $amount,
            'transaction_status' => $status,
            'payment_type' => 'qris',
            'transaction_time' => '2026-10-18 14:05:00',
            'settlement_time' => '2026-10-18 14:06:10',
        ], $extra);
        $payload['signature_key'] ??= hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::KEY);

        return $payload;
    }

    private function notify(array $payload)
    {
        return $this->postJson('/payments/midtrans/notification', $payload);
    }

    public function test_a_settlement_with_a_valid_signature_marks_the_order_paid(): void
    {
        $this->notify($this->payload('settlement'))->assertOk();

        $order = $this->order->fresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(KitchenStatus::Queued, $order->kitchen_status, 'masuk antrean dapur');
        $this->assertSame('2026-10-18 14:06:10', $order->paid_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

        $payment = $this->payment->fresh();
        $this->assertSame('settlement', $payment->transaction_status);
        $this->assertSame('qris', $payment->payment_type);
        $this->assertSame('ORD-ABCDEF-1', $payment->raw_payload['order_id']);
    }

    public function test_a_forged_notification_is_rejected_and_changes_nothing(): void
    {
        $forged = $this->payload('settlement', ['signature_key' => str_repeat('a', 128)]);

        $this->notify($forged)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
        $this->assertNull($this->payment->fresh()->transaction_status);
    }

    public function test_a_signature_made_with_the_wrong_key_is_rejected(): void
    {
        $payload = $this->payload('settlement');
        $payload['signature_key'] = hash('sha512', $payload['order_id'].'200'.$payload['gross_amount'].'kunci-yang-salah');

        $this->notify($payload)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_tampering_with_the_data_after_signing_is_detected(): void
    {
        $payload = $this->payload('pending');
        $payload['transaction_status'] = 'settlement'; // signature tidak mencakup field ini...
        $payload['gross_amount'] = '1.00';             // ...tapi mencakup gross_amount

        $this->notify($payload)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $payload = $this->payload('settlement');
        unset($payload['signature_key']);

        $this->notify($payload)->assertForbidden();
        $this->notify([])->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_nothing_is_accepted_when_the_server_key_is_not_configured(): void
    {
        $payload = $this->payload('settlement');
        config(['midtrans.server_key' => '']);

        $this->notify($payload)->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_an_amount_that_differs_from_the_database_is_rejected(): void
    {
        // Signature valid untuk nominal 100, tapi order ini bernilai 43.000.
        $this->notify($this->payload('settlement', amount: '100.00'))->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
        $this->assertNull($this->payment->fresh()->transaction_status);
    }

    public function test_the_same_notification_twice_has_no_double_effect(): void
    {
        $payload = $this->payload('settlement');

        $this->notify($payload)->assertOk();
        $paidAt = $this->order->fresh()->paid_at;

        $this->travel(5)->minutes();
        $this->notify($payload)->assertOk();

        $order = $this->order->fresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertTrue($order->paid_at->equalTo($paidAt), 'waktu bayar tidak bergeser');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_a_paid_order_is_never_downgraded_by_later_notifications(): void
    {
        $this->notify($this->payload('settlement'))->assertOk();

        $this->notify($this->payload('expire'))->assertOk();
        $this->notify($this->payload('cancel'))->assertOk();
        $this->notify($this->payload('pending'))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $this->order->fresh()->payment_status);
    }

    public function test_pending_keeps_the_order_waiting(): void
    {
        $this->notify($this->payload('pending', ['payment_type' => 'bank_transfer']))->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
        $this->assertSame('bank_transfer', $this->payment->fresh()->payment_type);
    }

    public function test_expire_cancel_and_deny_map_to_expired_and_failed(): void
    {
        $this->notify($this->payload('expire'))->assertOk();
        $this->assertSame(PaymentStatus::Expired, $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->paid_at);

        $this->order->forceFill(['payment_status' => PaymentStatus::Pending])->save();
        $this->notify($this->payload('cancel'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, $this->order->fresh()->payment_status);

        $this->order->forceFill(['payment_status' => PaymentStatus::Pending])->save();
        $this->notify($this->payload('deny'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, $this->order->fresh()->payment_status);
    }

    public function test_a_late_payment_after_expiry_still_counts_because_the_money_arrived(): void
    {
        $this->notify($this->payload('expire'))->assertOk();
        $this->assertSame(PaymentStatus::Expired, $this->order->fresh()->payment_status);

        $this->notify($this->payload('settlement'))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $this->order->fresh()->payment_status);
    }

    public function test_card_capture_pays_only_when_fraud_check_accepts(): void
    {
        $this->notify($this->payload('capture', ['payment_type' => 'credit_card', 'fraud_status' => 'challenge']))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status, 'masih ditinjau');

        $this->notify($this->payload('capture', ['payment_type' => 'credit_card', 'fraud_status' => 'accept']))->assertOk();
        $this->assertSame(PaymentStatus::Paid, $this->order->fresh()->payment_status);
    }

    public function test_one_expired_attempt_does_not_fail_an_order_with_another_attempt_still_running(): void
    {
        $second = $this->order->payments()->create([
            'midtrans_order_id' => 'ORD-ABCDEF-2', 'gross_amount' => 43000, 'snap_token' => 'tok2',
        ]);

        $this->notify($this->payload('expire'))->assertOk(); // percobaan 1 kedaluwarsa

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);

        $this->notify($this->payload('settlement', payment: $second))->assertOk(); // percobaan 2 dibayar

        $this->assertSame(PaymentStatus::Paid, $this->order->fresh()->payment_status);
    }

    public function test_an_unknown_order_id_is_acknowledged_but_ignored(): void
    {
        $payload = $this->payload('settlement', ['order_id' => 'payment_notif_test_123']);
        $payload['signature_key'] = hash('sha512', 'payment_notif_test_123'.$payload['status_code'].$payload['gross_amount'].self::KEY);

        $this->notify($payload)->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_only_the_webhook_is_exempt_from_csrf(): void
    {
        // Saat tes, Laravel melewati CSRF otomatis, jadi daftar pengecualiannya diperiksa langsung.
        // Server Midtrans tidak punya token CSRF, tapi route lain tetap harus dilindungi.
        $csrf = app(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $isExempt = new \ReflectionMethod($csrf, 'inExceptArray');

        $post = fn (string $uri) => \Illuminate\Http\Request::create($uri, 'POST');

        $this->assertTrue($isExempt->invoke($csrf, $post('/payments/midtrans/notification')));
        $this->assertFalse($isExempt->invoke($csrf, $post('/checkout')));
        $this->assertFalse($isExempt->invoke($csrf, $post('/order/ORD-ABCDEF/bayar')));
        $this->assertFalse($isExempt->invoke($csrf, $post('/login')));
        $this->assertFalse($isExempt->invoke($csrf, $post('/admin/categories')));
    }

    public function test_a_redirect_style_visit_to_the_status_page_cannot_mark_an_order_paid(): void
    {
        // Callback browser (onSuccess) hanya mengarahkan ke halaman status; tidak ada parameter yang bisa melunasi.
        $this->get('/order/ORD-ABCDEF?status=settlement&transaction_status=settlement&paid=1')->assertOk();
        $this->postJson('/order/ORD-ABCDEF/status', ['transaction_status' => 'settlement'])->assertStatus(405);

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }
}
