<?php

namespace Tests\Feature;

use App\Enums\KitchenStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Nomor antrean (A-001 / B-001) tampil di papan karyawan, riwayat karyawan, halaman status, dan riwayat pelanggan. */
class QueueNumberDisplayTest extends TestCase
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
    }

    private function withQueue(Order $order, int $number, string $type = 'dine_in'): Order
    {
        $order->forceFill(['order_type' => $type, 'queue_date' => today(), 'queue_number' => $number])->save();

        return $order->fresh();
    }

    public function test_the_board_card_carries_the_queue_label(): void
    {
        $this->withQueue($this->paidOrder($this->f), 7);
        $this->withQueue($this->paidOrder($this->f), 3, 'takeaway');

        $this->actingAs(User::factory()->staff()->create());
        $cards = collect($this->getJson('/staff/orders/data')->assertOk()->json('orders'))->keyBy('code');

        $this->assertSame('A-007', $cards['ORD-TEST01']['queue']);
        $this->assertSame('B-003', $cards['ORD-TEST02']['queue']);
        $this->get('/staff/orders')->assertOk()->assertSee('Nomor antrean');
    }

    public function test_the_staff_history_row_carries_the_queue_label(): void
    {
        $this->withQueue($this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Done, 'completed_at' => now()]), 12);

        $this->actingAs(User::factory()->staff()->create());
        $json = $this->getJson('/staff/riwayat/data?filter=done')->assertOk()->json();

        $this->assertSame('A-012', collect($json['orders'] ?? $json['rows'] ?? $json['data'])->first()['queue']);
    }

    public function test_the_status_page_shows_the_number_once_the_order_is_paid(): void
    {
        $paid = $this->withQueue($this->paidOrder($this->f), 5);
        $unpaid = $this->paidOrder($this->f, ['payment_status' => 'pending', 'paid_at' => null]);
        $this->withSession(['my_orders' => [$paid->code, $unpaid->code]]);

        $this->getJson("/order/{$paid->code}/status")->assertOk()->assertJsonPath('queue', 'A-005');
        $this->getJson("/order/{$unpaid->code}/status")->assertOk()->assertJsonPath('queue', null);

        $this->get("/order/{$paid->code}")->assertOk()->assertSee('Nomor antreanmu')->assertSee('A-005');
    }

    public function test_the_status_page_tells_takeaway_customers_to_wait_at_the_counter(): void
    {
        $order = $this->withQueue($this->paidOrder($this->f), 2, 'takeaway');
        $this->withSession(['my_orders' => [$order->code]]);

        $this->get("/order/{$order->code}")->assertOk()->assertSee('B-002')->assertSee('dipanggil di kasir');
    }

    public function test_the_customer_history_summary_carries_the_queue_label(): void
    {
        $user = User::factory()->create();
        $order = $this->withQueue($this->paidOrder($this->f), 9);
        $order->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)->withSession(['table_id' => $this->f['table']->id]);
        $summary = $this->getJson('/riwayat/data')->assertOk()->json('orders.0');

        $this->assertSame('A-009', $summary['queue']);
    }
}
