<?php

namespace Tests\Feature\Visitor;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/** Halaman order hanya untuk pemiliknya: tahu kode order saja tidak cukup. */
class OrderOwnershipTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => 'SB-Mid-server-test-key', 'midtrans.client_key' => 'SB-Mid-client-test']);
        $this->app->instance(PaymentGateway::class, new FakeGateway());
        $this->f = $this->menuFixture();
    }

    private function order(array $extra = []): Order
    {
        return Order::create([
            'code' => 'ORD-ABCDEF', 'table_id' => $this->f['table']->id, 'customer_name' => 'Budi',
            'order_type' => 'dine_in', 'subtotal' => 5000, 'total' => 5000, 'expires_at' => now()->addMinutes(15),
        ] + $extra);
    }

    public function test_the_creating_browser_can_open_its_order_pages(): void
    {
        $this->withSession(['table_id' => $this->f['table']->id])
            ->postJson('/checkout', [
                'order_type' => 'dine_in',
                'items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 1, 'options' => []]],
            ])->assertCreated();

        $code = Order::firstOrFail()->code;

        $this->get("/order/{$code}")->assertOk();
        $this->getJson("/order/{$code}/status")->assertOk();
    }

    public function test_another_browser_with_the_copied_url_is_turned_away(): void
    {
        $this->order();

        // Tanpa session pemilik, seperti URL yang ditempel di Chrome lain.
        $this->get('/order/ORD-ABCDEF')->assertRedirect(route('home'))->assertSessionHas('error');
        $this->get('/order/ORD-ABCDEF/bayar')->assertRedirect(route('home'));
        $this->get('/order/ORD-ABCDEF/hasil')->assertRedirect(route('home'));
        $this->getJson('/order/ORD-ABCDEF/status')->assertForbidden();
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertForbidden();
        $this->postJson('/order/ORD-ABCDEF/ganti-metode')->assertForbidden();
        $this->postJson('/order/ORD-ABCDEF/cek-pembayaran')->assertForbidden();
    }

    public function test_a_different_browser_that_owns_another_order_is_still_turned_away(): void
    {
        $this->order();

        $this->withSession(['my_orders' => ['ORD-ZZZZZZ']])->getJson('/order/ORD-ABCDEF/status')->assertForbidden();
    }

    public function test_the_logged_in_owner_can_open_the_order_from_any_browser(): void
    {
        $user = User::factory()->create();
        $this->order(['user_id' => $user->id]);

        $this->actingAs($user)->getJson('/order/ORD-ABCDEF/status')->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/order/ORD-ABCDEF/status')->assertForbidden();
    }

    public function test_a_draft_of_someone_else_is_not_overwritten(): void
    {
        $order = $this->order();

        $this->withSession(['table_id' => $this->f['table']->id])
            ->postJson('/checkout', [
                'order_type' => 'dine_in',
                'draft' => $order->code,
                'items' => [['menu_id' => $this->f['esTeh']->id, 'qty' => 1, 'options' => []]],
            ])->assertCreated();

        $this->assertSame(2, Order::count());
        $this->assertSame(5000, $order->fresh()->total);
    }
}
