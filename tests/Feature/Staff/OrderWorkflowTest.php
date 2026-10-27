<?php

namespace Tests\Feature\Staff;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use BuildsMenu;
    use BuildsOrders;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $f;

    private Order $order;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->f = $this->menuFixture();
        $this->staff = User::factory()->staff()->create();
        $this->order = $this->paidOrder($this->f);
        $this->actingAs($this->staff)->withSession(['my_orders' => [$this->order->code]]);
    }

    private function mie(): \App\Models\OrderItem
    {
        return $this->order->fresh()->items->firstWhere('menu_name', 'Mie Pedas');
    }

    private function esTeh(): \App\Models\OrderItem
    {
        return $this->order->fresh()->items->firstWhere('menu_name', 'Es Teh');
    }

    private function url(string $path): string
    {
        return "/staff/orders/{$this->order->code}/{$path}";
    }

    // ---- Mulai buat ---------------------------------------------------------------

    public function test_start_moves_the_order_to_cooking_and_never_touches_payment(): void
    {
        $this->postJson($this->url('start'))->assertOk()->assertJsonPath('order.kitchen', 'cooking');

        $order = $this->order->fresh();
        $this->assertSame(KitchenStatus::Cooking, $order->kitchen_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    public function test_starting_twice_is_harmless(): void
    {
        $this->postJson($this->url('start'))->assertOk();
        $this->postJson($this->url('start'))->assertOk()->assertJsonPath('order.kitchen', 'cooking');
    }

    public function test_an_unpaid_order_cannot_be_processed(): void
    {
        $unpaid = $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);

        $this->postJson("/staff/orders/{$unpaid->code}/start")->assertStatus(409);
        $this->postJson("/staff/orders/{$unpaid->code}/complete")->assertStatus(409);
        $this->postJson("/staff/orders/{$unpaid->code}/items/{$unpaid->items->first()->id}/serve", ['served' => true])->assertStatus(409);

        $this->assertSame(KitchenStatus::Queued, $unpaid->fresh()->kitchen_status);
    }

    // ---- Centang item ---------------------------------------------------------------

    public function test_checking_an_item_marks_it_served_and_starts_cooking(): void
    {
        $response = $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => true])->assertOk();

        $this->assertNotNull($this->mie()->served_at);
        $this->assertSame(KitchenStatus::Cooking, $this->order->fresh()->kitchen_status, 'item pertama otomatis memulai order');
        $response->assertJsonPath('order.servedCount', 1)->assertJsonPath('order.servableCount', 2);
    }

    public function test_an_item_can_be_unchecked_again(): void
    {
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => true])->assertOk();
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => false])->assertOk()->assertJsonPath('order.servedCount', 0);

        $this->assertNull($this->mie()->served_at);
    }

    public function test_checking_an_already_checked_item_keeps_the_original_time(): void
    {
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => true])->assertOk();
        $first = $this->mie()->served_at;

        $this->travel(3)->minutes();
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => true])->assertOk();

        $this->assertTrue($this->mie()->served_at->equalTo($first));
    }

    public function test_the_serve_request_must_say_whether_to_check_or_uncheck(): void
    {
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), [])->assertJsonValidationErrors('served');
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => 'mungkin'])->assertJsonValidationErrors('served');
    }

    public function test_an_item_of_another_order_cannot_be_changed_through_this_order_url(): void
    {
        $other = $this->paidOrder($this->f);
        $foreignItem = $other->items->first();

        $this->postJson($this->url("items/{$foreignItem->id}/serve"), ['served' => true])->assertNotFound();
        $this->postJson($this->url("items/{$foreignItem->id}/unavailable"), ['unavailable' => true])->assertNotFound();

        $this->assertNull($foreignItem->fresh()->served_at);
        $this->assertFalse($foreignItem->fresh()->is_unavailable);
    }

    // ---- Selesai --------------------------------------------------------------------

    public function test_complete_finishes_the_order_and_marks_the_remaining_items_served(): void
    {
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => true])->assertOk();

        $this->postJson($this->url('complete'))->assertOk()->assertJsonPath('order.kitchen', 'done')->assertJsonPath('order.servedCount', 2);

        $order = $this->order->fresh();
        $this->assertSame(KitchenStatus::Done, $order->kitchen_status);
        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($this->esTeh()->served_at);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    public function test_an_order_can_be_completed_straight_from_the_queue(): void
    {
        $this->postJson($this->url('complete'))->assertOk()->assertJsonPath('order.kitchen', 'done');
    }

    public function test_a_finished_order_is_locked(): void
    {
        $this->postJson($this->url('complete'))->assertOk();

        $this->postJson($this->url('complete'))->assertStatus(409)->assertJsonPath('message', 'Order ini sudah selesai.');
        $this->postJson($this->url('start'))->assertStatus(409);
        $this->postJson($this->url("items/{$this->mie()->id}/serve"), ['served' => false])->assertStatus(409);
        $this->postJson($this->url("items/{$this->mie()->id}/unavailable"), ['unavailable' => true])->assertStatus(409);

        $this->assertSame(KitchenStatus::Done, $this->order->fresh()->kitchen_status);
        $this->assertNotNull($this->mie()->served_at);
    }

    public function test_the_customer_status_page_follows_the_kitchen(): void
    {
        $this->getJson("/order/{$this->order->code}/status")->assertJson(['stage' => 'paid', 'step' => 2]);

        $this->postJson($this->url('start'))->assertOk();
        $this->getJson("/order/{$this->order->code}/status")->assertJson(['stage' => 'cooking', 'step' => 3]);

        $this->postJson($this->url('complete'))->assertOk();
        $this->getJson("/order/{$this->order->code}/status")->assertJson(['stage' => 'done', 'step' => 4]);
    }

    // ---- Item tidak tersedia (K-07) ----------------------------------------------------

    public function test_marking_an_item_unavailable_creates_a_refund_and_sells_out_the_menu(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])
            ->assertOk()
            ->assertJsonPath('order.refund', 'needed')
            ->assertJsonPath('order.refundAmount', 5000)
            ->assertJsonPath('order.servableCount', 1);

        $order = $this->order->fresh();
        $this->assertSame(RefundStatus::Needed, $order->refund_status);
        $this->assertSame(5000, $order->refund_amount);
        $this->assertTrue($this->esTeh()->is_unavailable);
        $this->assertFalse($this->f['esTeh']->fresh()->is_available, 'menu otomatis habis untuk pengunjung berikutnya');
        $this->assertTrue($this->f['mie']->fresh()->is_available, 'menu lain tidak ikut habis');
    }

    public function test_the_refund_is_the_sum_of_all_unavailable_items(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk();
        $this->postJson($this->url("items/{$this->mie()->id}/unavailable"), ['unavailable' => true])->assertOk()->assertJsonPath('order.refundAmount', 43000);
    }

    public function test_an_unavailable_item_can_no_longer_be_checked_and_loses_its_check(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/serve"), ['served' => true])->assertOk();

        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk();

        $this->assertNull($this->esTeh()->served_at);
        $this->postJson($this->url("items/{$this->esTeh()->id}/serve"), ['served' => true])->assertStatus(409);
    }

    public function test_completing_does_not_serve_unavailable_items(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk();

        $this->postJson($this->url('complete'))->assertOk();

        $this->assertNull($this->esTeh()->served_at);
        $this->assertNotNull($this->mie()->served_at);
    }

    public function test_marking_unavailable_by_mistake_can_be_undone_while_the_refund_is_not_paid_out(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk();

        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => false])
            ->assertOk()->assertJsonPath('order.refund', 'none')->assertJsonPath('order.refundAmount', 0);

        $order = $this->order->fresh();
        $this->assertSame(RefundStatus::None, $order->refund_status);
        $this->assertSame(0, $order->refund_amount);
        $this->assertFalse($this->esTeh()->is_unavailable);
    }

    public function test_repeating_the_same_unavailable_state_changes_nothing(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk();
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk()->assertJsonPath('order.refundAmount', 5000);
    }

    public function test_items_cannot_be_changed_after_the_refund_was_paid_out(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk();
        $this->postJson("/staff/riwayat/{$this->order->code}/refund")->assertOk();

        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => false])->assertStatus(409);
        $this->postJson($this->url("items/{$this->mie()->id}/unavailable"), ['unavailable' => true])->assertStatus(409);

        $this->assertSame(5000, $this->order->fresh()->refund_amount);
    }

    public function test_the_customer_is_told_about_the_refund(): void
    {
        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk();

        $this->getJson("/order/{$this->order->code}/status")->assertJson(['refund' => 'needed', 'refundAmount' => 5000]);
        $this->get("/order/{$this->order->code}")->assertSee('Tidak tersedia, akan di-refund')->assertSee('Ada item yang tidak tersedia');
    }

    public function test_a_menu_that_was_already_deleted_does_not_break_marking_unavailable(): void
    {
        $this->f['esTeh']->forceDelete();

        $this->postJson($this->url("items/{$this->esTeh()->id}/unavailable"), ['unavailable' => true])->assertOk()->assertJsonPath('order.refundAmount', 5000);
    }
}
