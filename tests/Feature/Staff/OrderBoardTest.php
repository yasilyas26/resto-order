<?php

namespace Tests\Feature\Staff;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class OrderBoardTest extends TestCase
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

    private function board(): array
    {
        $this->actingAs(User::factory()->staff()->create());

        return $this->getJson('/staff/orders/data')->assertOk()->json('orders');
    }

    // ---- akses ------------------------------------------------------------------

    public function test_only_staff_and_admin_can_open_the_board(): void
    {
        $this->get('/staff/orders')->assertRedirect('/login');
        $this->get('/staff/riwayat')->assertRedirect('/login');
        $this->getJson('/staff/orders/data')->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->get('/staff/orders')->assertForbidden();
        $this->get('/staff/riwayat')->assertForbidden();
        $this->getJson('/staff/orders/data')->assertForbidden();

        $this->actingAs(User::factory()->staff()->create())->get('/staff/orders')->assertOk()->assertSee('Pesan Aktif')->assertSee('Riwayat &amp; Refund', false);
        $this->actingAs(User::factory()->admin()->create())->get('/staff/orders')->assertOk();
    }

    public function test_every_board_action_is_closed_to_customers_and_guests(): void
    {
        $order = $this->paidOrder($this->f);
        $item = $order->items->first();
        $actions = [
            "/staff/orders/{$order->code}/start",
            "/staff/orders/{$order->code}/complete",
            "/staff/orders/{$order->code}/items/{$item->id}/serve",
            "/staff/orders/{$order->code}/items/{$item->id}/unavailable",
            "/staff/riwayat/{$order->code}/refund",
        ];

        foreach ($actions as $url) {
            $this->postJson($url)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());
        foreach ($actions as $url) {
            $this->postJson($url, ['served' => true, 'unavailable' => true])->assertForbidden();
        }

        $fresh = $order->fresh();
        $this->assertSame(KitchenStatus::Queued, $fresh->kitchen_status);
        $this->assertNull($fresh->items->first()->served_at);
    }

    public function test_the_board_page_embeds_the_first_batch_of_orders(): void
    {
        $this->paidOrder($this->f);

        $this->actingAs(User::factory()->staff()->create())->get('/staff/orders')
            ->assertOk()
            ->assertSee('ORD-TEST01')
            ->assertSee('Dimas Anggara');
    }

    // ---- siapa yang tampil -------------------------------------------------------

    public function test_only_paid_orders_appear_on_the_board(): void
    {
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Paid]);
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Expired, 'paid_at' => null]);
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Failed, 'paid_at' => null]);

        $codes = collect($this->board())->pluck('code')->all();

        $this->assertSame(['ORD-TEST01'], $codes);
    }

    public function test_queued_cooking_and_recently_finished_orders_show_but_old_finished_ones_do_not(): void
    {
        $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Queued]);
        $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Cooking]);
        $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Done, 'completed_at' => now()->subHour()]);
        $this->paidOrder($this->f, ['kitchen_status' => KitchenStatus::Done, 'completed_at' => now()->subHours(4)]);

        $codes = collect($this->board())->pluck('code')->all();

        $this->assertSame(['ORD-TEST01', 'ORD-TEST02', 'ORD-TEST03'], $codes);
    }

    public function test_the_longest_waiting_order_comes_first(): void
    {
        $this->paidOrder($this->f, ['paid_at' => now()->subMinutes(2)]);
        $this->paidOrder($this->f, ['paid_at' => now()->subMinutes(30)]);
        $this->paidOrder($this->f, ['paid_at' => now()->subMinutes(10)]);

        $this->assertSame(['ORD-TEST02', 'ORD-TEST03', 'ORD-TEST01'], collect($this->board())->pluck('code')->all());
    }

    // ---- isi kartu ---------------------------------------------------------------

    public function test_a_card_has_everything_the_kitchen_needs_and_nothing_private(): void
    {
        $this->paidOrder($this->f);

        $this->actingAs(User::factory()->staff()->create());
        $response = $this->getJson('/staff/orders/data')->assertOk();
        $card = $response->json('orders.0');

        $this->assertSame('A07', $card['table']);
        $this->assertSame('Dimas Anggara', $card['customer']);
        $this->assertSame('ORD-TEST01', $card['code']);
        $this->assertSame('Makan di Tempat', $card['orderType']);
        $this->assertSame('queued', $card['kitchen']);
        $this->assertSame(0, $card['servedCount']);
        $this->assertSame(2, $card['servableCount']);
        $this->assertNotNull($card['paidAt']);

        $mie = $card['items'][0];
        $this->assertSame('Mie Pedas', $mie['name']);
        $this->assertSame(2, $mie['qty']);
        $this->assertSame(['Level 3', 'Telur'], $mie['options']);
        $this->assertSame('cabai dipisah', $mie['note']);
        $this->assertSame('Mie', $mie['station']);
        $this->assertFalse($mie['served']);
        $this->assertFalse($mie['unavailable']);

        $this->assertStringNotContainsString('rahasia@kontak.test', $response->getContent(), 'kontak pelanggan tidak untuk papan');
    }

    public function test_additional_orders_from_the_same_table_are_separate_cards_labelled_by_sequence(): void
    {
        $other = $this->secondTable();
        $this->paidOrder($this->f, ['paid_at' => now()->subMinutes(30)]);
        $this->paidOrder($this->f, ['paid_at' => now()->subMinutes(20), 'table_id' => $other->id]);
        $this->paidOrder($this->f, ['paid_at' => now()->subMinutes(10)]);

        $cards = collect($this->board())->keyBy('code');

        $this->assertCount(3, $cards, 'tidak digabung jadi satu kartu');
        $this->assertSame(1, $cards['ORD-TEST01']['sequence']);
        $this->assertSame(1, $cards['ORD-TEST02']['sequence'], 'meja lain mulai dari 1');
        $this->assertSame(2, $cards['ORD-TEST03']['sequence'], 'order ke-2 dari meja A07');
    }

    public function test_the_sequence_only_counts_orders_paid_today_and_ignores_unpaid_ones(): void
    {
        $this->paidOrder($this->f, ['paid_at' => now()->subDay(), 'kitchen_status' => KitchenStatus::Done, 'completed_at' => now()->subDay()]);
        $this->paidOrder($this->f, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        $this->paidOrder($this->f, ['paid_at' => now()->subMinutes(5)]);

        $card = collect($this->board())->firstWhere('code', 'ORD-TEST03');

        $this->assertSame(1, $card['sequence']);
    }

    public function test_item_station_survives_the_menu_being_renamed(): void
    {
        $order = $this->paidOrder($this->f);
        $this->f['category']->update(['name' => 'Nama Baru']);
        $this->f['mie']->update(['name' => 'Mie Super']);

        $card = collect($this->board())->firstWhere('code', $order->code);

        $this->assertSame('Mie', $card['items'][0]['station']);
        $this->assertSame('Mie Pedas', $card['items'][0]['name']);
    }

    public function test_the_board_groups_orders_by_status_from_top_to_bottom(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $html = $this->get('/staff/orders')->assertOk()->getContent();

        $queued = strpos($html, 'id="grup-queued"');
        $cooking = strpos($html, 'id="grup-cooking"');
        $done = strpos($html, 'id="grup-done"');

        $this->assertNotFalse($queued);
        $this->assertLessThan($cooking, $queued, 'Belum Dibuat di atas Sedang Dibuat');
        $this->assertLessThan($done, $cooking, 'Sedang Dibuat di atas Selesai');
        $this->assertStringContainsString('Belum Dibuat', $html);
        $this->assertStringContainsString("group('queued')", $html);
    }

    public function test_only_an_admin_sees_the_button_back_to_the_admin_dashboard_on_staff_pages(): void
    {
        foreach (['/staff/orders', '/staff/riwayat'] as $url) {
            $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk()
                ->assertSee('href="'.url('/admin').'"', false)->assertSee('Admin');

            $this->actingAs(User::factory()->staff()->create())->get($url)->assertOk()
                ->assertDontSee('href="'.url('/admin').'"', false);
        }
    }
}
