<?php

namespace Tests\Feature\Visitor;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = $this->menuFixture();
        // Riwayat butuh meja yang sudah dipilih, sama seperti menu.
        $this->withSession(['table_id' => $this->f['table']->id]);
    }

    private function makeOrder(string $code, ?User $user = null, string $name = 'Budi', array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'code' => $code, 'table_id' => $this->f['table']->id, 'user_id' => $user?->id,
            'customer_name' => $name, 'customer_contact' => 'rahasia@kontak.test', 'order_type' => 'dine_in',
            'subtotal' => 20000, 'total' => 20000,
        ], $attributes));

        $item = $order->items()->create([
            'menu_id' => $this->f['mie']->id, 'menu_name' => 'Mie Pedas', 'unit_price' => 15000,
            'qty' => 1, 'note' => 'jangan pedas', 'line_total' => 16000,
        ]);
        $item->options()->create(['group_name' => 'Level pedas', 'option_name' => 'Level 3', 'extra_price' => 1000]);

        return $order;
    }

    // ---- tamu -------------------------------------------------------------------

    public function test_guest_history_page_renders_with_the_google_invitation(): void
    {
        $this->get('/riwayat')->assertOk()
            ->assertSee('Riwayat Pesanan Anda')
            ->assertSee('Masuk atau Daftar')
            ->assertSee('Panduan Pesanan Tamu');
    }

    public function test_guest_lookup_returns_only_the_requested_codes(): void
    {
        $this->makeOrder('ORD-AAAAAA');
        $this->makeOrder('ORD-BBBBBB');
        $this->makeOrder('ORD-CCCCCC');

        $response = $this->postJson('/riwayat/lookup', ['codes' => ['ORD-AAAAAA', 'ORD-CCCCCC', 'ORD-ZZZZZZ']])->assertOk();

        $this->assertEqualsCanonicalizing(['ORD-AAAAAA', 'ORD-CCCCCC'], collect($response->json('orders'))->pluck('code')->all());
    }

    public function test_lookup_summary_has_what_the_card_needs_but_no_personal_data(): void
    {
        $this->makeOrder('ORD-AAAAAA', name: 'Budi Rahasia');

        $response = $this->postJson('/riwayat/lookup', ['codes' => ['ORD-AAAAAA']])->assertOk();

        $order = $response->json('orders.0');
        $this->assertSame('ORD-AAAAAA', $order['code']);
        $this->assertSame('A07', $order['table']);
        $this->assertSame(20000, $order['total']);
        $this->assertSame('waiting', $order['stage']);
        $this->assertTrue($order['active']);
        $this->assertSame(url('/order/ORD-AAAAAA'), $order['url']);
        $this->assertSame([['name' => 'Mie Pedas', 'qty' => 1, 'options' => 'Level 3', 'note' => 'jangan pedas']], $order['items']);

        $raw = $response->getContent();
        $this->assertStringNotContainsString('Budi', $raw);
        $this->assertStringNotContainsString('rahasia@kontak.test', $raw);
    }

    public function test_one_bad_code_does_not_break_the_rest_of_the_history(): void
    {
        $this->makeOrder('ORD-AAAAAA');

        // Kode usang atau rusak di localStorage: diabaikan, order yang sah tetap muncul.
        $response = $this->postJson('/riwayat/lookup', ['codes' => ['bukan-kode', 'ORD-AAAAAA', 'ORD-4M2P1X', "x' OR 1=1 --"]])->assertOk();

        $this->assertSame(['ORD-AAAAAA'], collect($response->json('orders'))->pluck('code')->all());
    }

    public function test_lookup_rejects_empty_oversized_or_non_text_input(): void
    {
        $this->postJson('/riwayat/lookup', ['codes' => []])->assertStatus(422);
        $this->postJson('/riwayat/lookup', [])->assertStatus(422);
        $this->postJson('/riwayat/lookup', ['codes' => 'ORD-AAAAAA'])->assertStatus(422);
        $this->postJson('/riwayat/lookup', ['codes' => [['ORD-AAAAAA']]])->assertStatus(422);
        $this->postJson('/riwayat/lookup', ['codes' => [str_repeat('A', 21)]])->assertStatus(422);

        $tooMany = array_fill(0, 31, 'ORD-AAAAAA');
        $this->postJson('/riwayat/lookup', ['codes' => $tooMany])->assertStatus(422);
    }

    public function test_lookup_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/riwayat/lookup', ['codes' => ['ORD-AAAAAA']])->assertOk();
        }

        $this->postJson('/riwayat/lookup', ['codes' => ['ORD-AAAAAA']])->assertStatus(429);
    }

    public function test_guest_cannot_use_the_account_history_endpoint(): void
    {
        $this->getJson('/riwayat/data')->assertUnauthorized();
    }

    // ---- pelanggan --------------------------------------------------------------

    public function test_customer_history_lists_only_their_own_orders(): void
    {
        $me = User::factory()->create(['name' => 'Siti Aminah']);
        $someoneElse = User::factory()->create();
        $this->makeOrder('ORD-MINE22', $me);
        $this->makeOrder('ORD-OTHER2', $someoneElse);
        $this->makeOrder('ORD-GUEST2');

        $html = $this->actingAs($me)->get('/riwayat')->assertOk()->getContent();

        $this->assertStringContainsString('ORD-MINE22', $html);
        $this->assertStringNotContainsString('ORD-OTHER2', $html);
        $this->assertStringNotContainsString('ORD-GUEST2', $html, 'order tamu sebelum login tidak diklaim ke akun');
        $this->assertStringContainsString('Siti Aminah', $html);
        $this->assertStringContainsString('Keluar dari Akun', $html);
    }

    public function test_profile_card_counts_only_paid_orders(): void
    {
        $me = User::factory()->create();
        $this->makeOrder('ORD-PAID11', $me)->forceFill(['payment_status' => PaymentStatus::Paid])->save();
        $this->makeOrder('ORD-PAID22', $me)->forceFill(['payment_status' => PaymentStatus::Paid])->save();
        $this->makeOrder('ORD-WAIT11', $me);

        $this->actingAs($me)->get('/riwayat')->assertOk()->assertSeeInOrder(['>2<', 'Total Orderan'], false);
    }

    public function test_load_more_pages_through_the_customer_history_newest_first(): void
    {
        $me = User::factory()->create();
        foreach (range(1, 12) as $i) {
            $this->makeOrder(sprintf('ORD-HIST%02d', $i), $me)
                ->forceFill(['created_at' => now()->subMinutes(100 - $i)])->save();
        }

        $page1 = $this->actingAs($me)->getJson('/riwayat/data?page=1')->assertOk();
        $this->assertCount(10, $page1->json('orders'));
        $this->assertSame(2, $page1->json('nextPage'));
        $this->assertSame('ORD-HIST12', $page1->json('orders.0.code'), 'terbaru dulu');

        $page2 = $this->actingAs($me)->getJson('/riwayat/data?page=2')->assertOk();
        $this->assertCount(2, $page2->json('orders'));
        $this->assertNull($page2->json('nextPage'));
    }

    public function test_profile_link_is_shown_for_every_customer_and_google_badge_only_for_google_accounts(): void
    {
        $withPassword = User::factory()->create();
        $googleOnly = User::factory()->create(['password' => null, 'google_id' => 'g-123']);

        $this->actingAs($withPassword)->get('/riwayat')->assertSee('Kelola Profil');
        $this->actingAs($googleOnly)->get('/riwayat')->assertSee('Kelola Profil')->assertSee('Akun Google');
    }

    public function test_dashboard_sends_customers_to_the_history_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/riwayat');
    }

    public function test_login_and_register_pages_use_the_new_design(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Masuk Sekarang')->assertSee('Lupa Password?')->assertSee('Lanjut dengan Google')
            ->assertDontSee('Lanjut Memesan Sebagai Tamu')->assertSee('Daftar Sekarang');

        $this->get('/register')->assertOk()
            ->assertSee('Buat Akun Pelanggan')->assertSee('Kekuatan Sandi')->assertSee('Daftar Akun Baru')->assertSee('Masuk di Sini');
    }
}
