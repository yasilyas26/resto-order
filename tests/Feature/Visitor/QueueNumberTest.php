<?php

namespace Tests\Feature\Visitor;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\TestCase;

/** Nomor antrean dibuat saat order lunas: satu deret per jenis, mulai dari 1 lagi tiap hari. */
class QueueNumberTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test-key';

    private int $tableId;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['midtrans.server_key' => self::KEY]);
        $this->tableId = $this->menuFixture()['table']->id;
    }

    private function newOrder(string $type): Payment
    {
        $n = ++$this->seq;
        $order = Order::create([
            'code' => "ORD-{$n}", 'table_id' => $this->tableId, 'customer_name' => 'Budi',
            'order_type' => $type, 'subtotal' => 10000, 'total' => 10000, 'expires_at' => now()->addMinutes(15),
        ]);

        return $order->payments()->create(['midtrans_order_id' => "ORD-{$n}-1", 'gross_amount' => 10000, 'snap_token' => 't']);
    }

    private function pay(Payment $payment, string $time = '2026-10-18 14:06:10'): void
    {
        $payload = [
            'order_id' => $payment->midtrans_order_id, 'status_code' => '200', 'gross_amount' => '10000.00',
            'transaction_status' => 'settlement', 'payment_type' => 'qris', 'settlement_time' => $time,
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].'200'.$payload['gross_amount'].self::KEY);

        $this->postJson('/payments/midtrans/notification', $payload)->assertOk();
    }

    public function test_an_unpaid_order_has_no_queue_number(): void
    {
        $payment = $this->newOrder('dine_in');

        $this->assertNull($payment->order->fresh()->queueLabel());
    }

    public function test_each_order_type_has_its_own_sequence(): void
    {
        $a1 = $this->newOrder('dine_in');
        $b1 = $this->newOrder('takeaway');
        $a2 = $this->newOrder('dine_in');

        $this->pay($a1);
        $this->pay($b1);
        $this->pay($a2);

        $this->assertSame('A-001', $a1->order->fresh()->queueLabel());
        $this->assertSame('B-001', $b1->order->fresh()->queueLabel());
        $this->assertSame('A-002', $a2->order->fresh()->queueLabel());
    }

    public function test_numbers_follow_payment_order_not_creation_order(): void
    {
        $first = $this->newOrder('dine_in');
        $second = $this->newOrder('dine_in');

        $this->pay($second);
        $this->pay($first);

        $this->assertSame('A-001', $second->order->fresh()->queueLabel());
        $this->assertSame('A-002', $first->order->fresh()->queueLabel());
    }

    public function test_the_sequence_restarts_each_day(): void
    {
        $today = $this->newOrder('dine_in');
        $tomorrow = $this->newOrder('dine_in');

        $this->pay($today, '2026-10-18 14:00:00');
        $this->pay($tomorrow, '2026-10-19 09:00:00');

        $this->assertSame('A-001', $today->order->fresh()->queueLabel());
        $this->assertSame('A-001', $tomorrow->order->fresh()->queueLabel());
    }

    public function test_a_repeated_notification_does_not_change_the_number(): void
    {
        $payment = $this->newOrder('takeaway');

        $this->pay($payment);
        $this->pay($payment);

        $this->assertSame('B-001', $payment->order->fresh()->queueLabel());
        $this->assertSame(1, (int) \DB::table('queue_counters')->value('last_number'));
    }
}
