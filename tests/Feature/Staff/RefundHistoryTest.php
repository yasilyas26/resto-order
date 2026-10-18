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

class RefundHistoryTest extends TestCase
{
    use BuildsMenu;
    use BuildsOrders;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $f;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->f = $this->menuFixture();
        $this->cashier = User::factory()->staff()->create(['name' => 'Siti Kasir']);
        $this->actingAs($this->cashier);
    }

    private function needsRefund(): Order
    {
        $order = $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Cooking]);
        $order->forceFill(['refund_status' => RefundStatus::Needed, 'refund_amount' => 5000])->save();
        $order->items->firstWhere('menu_name', 'Es Teh')->forceFill(['is_unavailable' => true])->save();

        return $order;
    }

    public function test_cashier_marks_a_refund_as_handed_over_and_it_records_who_and_when(): void
    {
        $order = $this->needsRefund();

        $this->postJson("/staff/riwayat/{$order->code}/refund")
            ->assertOk()
            ->assertJsonPath('order.refund', 'refunded')
            ->assertJsonPath('order.refundedBy', 'Siti Kasir')
            ->assertJsonPath('counts.refund_needed', 0)
            ->assertJsonPath('counts.refunded', 1);

        $order = $order->fresh();
        $this->assertSame(RefundStatus::Refunded, $order->refund_status);
        $this->assertSame($this->cashier->id, $order->refunded_by);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame(5000, $order->refund_amount, 'nominal tetap tercatat untuk laporan');
    }

    public function test_an_order_without_a_pending_refund_cannot_be_refunded(): void
    {
        $order = $this->paidOrder($this->f);

        $this->postJson("/staff/riwayat/{$order->code}/refund")->assertStatus(409);

        $this->assertSame(RefundStatus::None, $order->fresh()->refund_status);
        $this->assertNull($order->fresh()->refunded_by);
    }

    public function test_the_same_refund_cannot_be_paid_out_twice(): void
    {
        $order = $this->needsRefund();
        $this->postJson("/staff/riwayat/{$order->code}/refund")->assertOk();
        $first = $order->fresh()->refunded_at;

        $this->actingAs(User::factory()->staff()->create(['name' => 'Kasir Lain']));
        $this->travel(10)->minutes();
        $this->postJson("/staff/riwayat/{$order->code}/refund")->assertStatus(409);

        $this->assertSame($this->cashier->id, $order->fresh()->refunded_by, 'pencatat pertama tidak tertimpa');
        $this->assertTrue($order->fresh()->refunded_at->equalTo($first));
    }

    public function test_refund_needs_the_order_to_be_paid(): void
    {
        $order = $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        $order->forceFill(['refund_status' => RefundStatus::Needed, 'refund_amount' => 1000])->save();

        $this->getJson('/staff/riwayat/data?filter=refund_needed')->assertJsonPath('counts.refund_needed', 0);
    }

    public function test_history_page_defaults_to_pending_refunds_when_there_are_some(): void
    {
        $this->needsRefund();

        $this->get('/staff/riwayat')->assertOk()->assertSee('Perlu Refund')->assertSee('ORD-TEST01');
    }

    public function test_history_lists_each_filter_with_correct_counts(): void
    {
        $pending = $this->needsRefund();
        $paidOut = $this->needsRefund();
        $this->postJson("/staff/riwayat/{$paidOut->code}/refund")->assertOk();
        $done = $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Done, 'completed_at' => now()->subDay()]);

        $needed = $this->getJson('/staff/riwayat/data?filter=refund_needed')->assertOk();
        $this->assertSame([$pending->code], collect($needed->json('orders'))->pluck('code')->all());
        $this->assertSame(['refund_needed' => 1, 'refunded' => 1, 'done' => 1], $needed->json('counts'));

        $refunded = $this->getJson('/staff/riwayat/data?filter=refunded')->assertOk();
        $this->assertSame([$paidOut->code], collect($refunded->json('orders'))->pluck('code')->all());
        $this->assertSame('Siti Kasir', $refunded->json('orders.0.refundedBy'));

        $finished = $this->getJson('/staff/riwayat/data?filter=done')->assertOk();
        $this->assertSame([$done->code], collect($finished->json('orders'))->pluck('code')->all());
    }

    public function test_a_refund_row_names_the_unavailable_items_and_hides_contact_details(): void
    {
        $this->needsRefund();

        $response = $this->getJson('/staff/riwayat/data?filter=refund_needed')->assertOk();

        $this->assertSame(['1x Es Teh'], $response->json('orders.0.unavailableItems'));
        $this->assertSame(5000, $response->json('orders.0.refundAmount'));
        $this->assertStringNotContainsString('rahasia@kontak.test', $response->getContent());
    }

    public function test_history_is_paginated_newest_first(): void
    {
        foreach (range(1, 17) as $i) {
            $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Done, 'completed_at' => now()->subMinutes(100 - $i)]);
        }

        $page1 = $this->getJson('/staff/riwayat/data?filter=done&page=1')->assertOk();
        $this->assertCount(15, $page1->json('orders'));
        $this->assertSame(2, $page1->json('nextPage'));
        $this->assertSame('ORD-TEST17', $page1->json('orders.0.code'));

        $page2 = $this->getJson('/staff/riwayat/data?filter=done&page=2')->assertOk();
        $this->assertCount(2, $page2->json('orders'));
        $this->assertNull($page2->json('nextPage'));
    }

    public function test_the_filter_is_validated(): void
    {
        $this->getJson('/staff/riwayat/data')->assertJsonValidationErrors('filter');
        $this->getJson('/staff/riwayat/data?filter=semua')->assertJsonValidationErrors('filter');
        $this->getJson('/staff/riwayat/data?filter=done&page=0')->assertJsonValidationErrors('page');
    }
}
