<?php

namespace Tests\Feature\Console;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\TestCase;

/** midtrans:simulate meniru notifikasi Midtrans lewat handler yang sama dengan webhook (khusus lokal). */
class SimulateMidtransPaymentTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => 'SB-Mid-server-test-key']);

        $f = $this->menuFixture();
        $this->order = Order::create([
            'code' => 'ORD-ABCDEF', 'table_id' => $f['table']->id, 'customer_name' => 'Meja A07',
            'order_type' => 'dine_in', 'subtotal' => 9500, 'total' => 9500, 'expires_at' => now()->addMinutes(15),
        ]);
        $this->order->payments()->create(['midtrans_order_id' => 'ORD-ABCDEF-1', 'gross_amount' => 9500, 'snap_token' => 't']);
    }

    public function test_it_marks_the_order_paid_and_gives_it_a_queue_number(): void
    {
        $this->artisan('midtrans:simulate', ['code' => 'ORD-ABCDEF'])->assertSuccessful();

        $order = $this->order->fresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('A-001', $order->queueLabel());
        $this->assertSame('settlement', $order->payments()->first()->transaction_status);
    }

    public function test_the_code_is_case_insensitive_and_a_full_midtrans_id_works_too(): void
    {
        $this->artisan('midtrans:simulate', ['code' => 'ord-abcdef-1'])->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $this->order->fresh()->payment_status);
    }

    public function test_other_statuses_can_be_simulated(): void
    {
        $this->artisan('midtrans:simulate', ['code' => 'ORD-ABCDEF', '--status' => 'expire'])->assertSuccessful();

        $this->assertSame(PaymentStatus::Expired, $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->queueLabel());
    }

    public function test_an_unknown_status_or_order_is_rejected(): void
    {
        $this->artisan('midtrans:simulate', ['code' => 'ORD-ABCDEF', '--status' => 'ngawur'])->assertFailed();
        $this->artisan('midtrans:simulate', ['code' => 'ORD-TIDAKADA'])->assertFailed();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_an_order_without_any_payment_attempt_explains_what_to_do(): void
    {
        $this->order->payments()->delete();

        $this->artisan('midtrans:simulate', ['code' => 'ORD-ABCDEF'])
            ->expectsOutputToContain('Bayar Sekarang')
            ->assertFailed();
    }

    public function test_it_refuses_to_run_without_a_server_key_or_against_production(): void
    {
        config(['midtrans.server_key' => '']);
        $this->artisan('midtrans:simulate', ['code' => 'ORD-ABCDEF'])->assertFailed();

        config(['midtrans.server_key' => 'kunci', 'midtrans.is_production' => true]);
        $this->artisan('midtrans:simulate', ['code' => 'ORD-ABCDEF'])->assertFailed();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    public function test_it_refuses_to_run_outside_local_environments(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('midtrans:simulate', ['code' => 'ORD-ABCDEF'])->assertFailed();

        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }
}
