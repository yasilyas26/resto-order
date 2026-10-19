<?php

namespace Tests\Feature\Visitor;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/** Halaman Pilih Metode Pembayaran dan halaman hasil (berhasil, gagal, kedaluwarsa, menunggu). */
class PaymentPagesTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private FakeGateway $gateway;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => 'SB-Mid-server-test-key', 'midtrans.client_key' => 'SB-Mid-client-test']);
        $this->gateway = new FakeGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);

        $f = $this->menuFixture();
        $this->order = Order::create([
            'code' => 'ORD-ABCDEF', 'table_id' => $f['table']->id, 'customer_name' => 'Budi',
            'order_type' => 'dine_in', 'subtotal' => 38000, 'total' => 38000, 'expires_at' => now()->addMinutes(15),
        ]);
        $this->order->items()->create(['menu_id' => $f['mie']->id, 'menu_name' => 'Mie Pedas', 'unit_price' => 15000, 'qty' => 2, 'line_total' => 38000]);
    }

    private function setStatus(PaymentStatus $status, array $extra = []): void
    {
        $this->order->forceFill(['payment_status' => $status] + $extra)->save();
    }

    // ---- Pilih metode ------------------------------------------------------------

    public function test_method_page_lists_methods_and_summary_without_loading_snap(): void
    {
        $this->get('/order/ORD-ABCDEF/bayar')->assertOk()
            ->assertSee('Pilih Metode Pembayaran')
            ->assertSee('QRIS')->assertSee('GoPay')->assertSee('ShopeePay')->assertSee('Transfer Virtual Account')
            ->assertSee('Ringkasan Pesanan')->assertSee('Mie Pedas')->assertSee('2x')->assertSee('Rp 38.000')
            ->assertDontSee('snap.js', false)->assertDontSee('SB-Mid-server', false); // tanpa popup Snap lagi
    }

    public function test_summary_shows_menu_description_options_and_note_and_the_pay_button_comes_after_the_methods(): void
    {
        $item = $this->order->items()->first();
        $item->menu->update(['description' => 'Mie pedas dengan ayam cincang dan sambal khas.']);
        $item->update(['note' => 'tanpa bawang']);
        $item->options()->create(['group_name' => 'Level pedas', 'option_name' => 'Level 4', 'extra_price' => 0]);
        $item->options()->create(['group_name' => 'Topping', 'option_name' => 'Telur', 'extra_price' => 3000]);

        $html = $this->get('/order/ORD-ABCDEF/bayar')->assertOk()
            ->assertSee('Mie pedas dengan ayam cincang dan sambal khas.')
            ->assertSee('Level pedas: Level 4')->assertSee('Topping: Telur')->assertSee('+Rp 3.000')
            ->assertSee('Catatan:', false)->assertSee('tanpa bawang')
            ->getContent();

        // Urutan di halaman: ringkasan (kiri) -> metode -> tombol bayar di paling bawah.
        $this->assertLessThan(strpos($html, 'metode-title'), strpos($html, 'ringkasan-title'));
        $this->assertLessThan(strpos($html, '@click="pay()"'), strpos($html, 'metode-title'));
    }

    public function test_method_page_redirects_to_the_result_when_already_paid(): void
    {
        $this->setStatus(PaymentStatus::Paid, ['paid_at' => now()]);

        $this->get('/order/ORD-ABCDEF/bayar')->assertRedirect('/order/ORD-ABCDEF/hasil');
    }

    public function test_unknown_method_is_rejected_and_no_method_keeps_the_old_behaviour(): void
    {
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bitcoin'])->assertUnprocessable();

        $this->postJson('/order/ORD-ABCDEF/bayar')->assertOk();
        $this->assertNull($this->gateway->tokenRequests[0]['enabledPayments']);
    }

    public function test_paying_an_already_paid_order_points_to_the_result_page(): void
    {
        $this->setStatus(PaymentStatus::Paid, ['paid_at' => now()]);

        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertConflict()
            ->assertJsonPath('result_url', url('/order/ORD-ABCDEF/hasil'));
    }

    // ---- Halaman hasil -----------------------------------------------------------

    public function test_success_page_shows_queue_number_and_never_the_customer_contact(): void
    {
        $this->setStatus(PaymentStatus::Paid, ['paid_at' => now(), 'queue_number' => 7, 'queue_date' => today(), 'customer_contact' => 'rahasia@kontak.test']);

        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()
            ->assertSee('Pembayaran Berhasil')->assertSee('A-007')->assertSee('Lacak Pesanan')
            ->assertDontSee('rahasia@kontak.test')->assertDontSee('Coba Bayar Lagi');
    }

    public function test_failed_page_offers_to_try_again(): void
    {
        $this->setStatus(PaymentStatus::Failed);

        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()
            ->assertSee('Pembayaran Gagal')->assertSee('Coba Bayar Lagi')
            ->assertSee('/order/ORD-ABCDEF/bayar', false)->assertDontSee('Pembayaran Berhasil');
    }

    public function test_expired_page_offers_to_pay_again_both_for_the_status_and_for_a_lapsed_deadline(): void
    {
        $this->setStatus(PaymentStatus::Expired);
        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()->assertSee('Waktu Pembayaran Habis')->assertSee('Bayar Lagi');

        // Masih "pending" di database tapi batas bayar sudah lewat (webhook expire belum datang).
        $this->setStatus(PaymentStatus::Pending, ['expires_at' => now()->subMinute()]);
        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()->assertSee('Waktu Pembayaran Habis');
    }

    public function test_waiting_page_polls_and_links_back_to_the_method_page(): void
    {
        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()
            ->assertSee('Menunggu Konfirmasi Pembayaran')->assertSee('Pilih Metode Pembayaran')
            ->assertSee('paymentResult', false);
    }

    public function test_result_follows_the_server_state_not_the_url(): void
    {
        // Parameter sukses di URL tidak mengubah apa pun: status lunas hanya dari webhook.
        $this->get('/order/ORD-ABCDEF/hasil?transaction_status=settlement&status_code=200')->assertOk()
            ->assertDontSee('Pembayaran Berhasil');
    }

    public function test_failed_order_can_open_payment_again_with_a_new_attempt(): void
    {
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertOk();
        $this->order->payments()->update(['transaction_status' => 'deny']);
        $this->setStatus(PaymentStatus::Failed);

        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertOk();

        $this->assertSame(2, $this->order->payments()->count());
        $this->assertSame(PaymentStatus::Pending, $this->order->fresh()->payment_status);
    }

    // ---- Hitung mundur -----------------------------------------------------------

    public function test_pressing_pay_after_the_deadline_starts_a_fresh_clock(): void
    {
        config(['midtrans.expiry_minutes' => 5]);
        $this->order->forceFill(['expires_at' => null])->save();
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertOk();

        $this->travel(6)->minutes();
        $this->setStatus(PaymentStatus::Expired);
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertOk();

        $this->assertEqualsWithDelta(5 * 60, now()->diffInSeconds($this->order->fresh()->expires_at, true), 3);
    }

    public function test_status_only_exposes_the_deadline_while_waiting(): void
    {
        $this->assertNotNull($this->getJson('/order/ORD-ABCDEF/status')->json('expiresAt'));

        $this->setStatus(PaymentStatus::Paid, ['paid_at' => now()]);
        $this->assertNull($this->getJson('/order/ORD-ABCDEF/status')->json('expiresAt'));
    }

    public function test_status_page_sends_change_method_to_the_method_page(): void
    {
        $this->get('/order/ORD-ABCDEF')->assertOk()
            ->assertSee('href="'.url('/order/ORD-ABCDEF/bayar?ganti=1').'"', false)
            ->assertSee('Ganti metode bayar')
            ->assertDontSee('change-method', false)
            ->assertDontSee('ganti-metode', false);
    }

    // ---- Cara bayar (QR / VA / minimarket) ---------------------------------------

    private function pending(array $payload): void
    {
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertOk();
        $this->order->payments()->update([
            'transaction_status' => 'pending',
            'payment_type' => $payload['payment_type'],
            'raw_payload' => $payload + ['order_id' => 'ORD-ABCDEF-1', 'transaction_status' => 'pending', 'gross_amount' => '38000.00'],
        ]);
    }

    public function test_waiting_page_shows_the_virtual_account_number_from_midtrans(): void
    {
        $this->pending(['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bca', 'va_number' => '81234567890']]]);

        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()->assertSee('81234567890')->assertSee('Virtual Account BCA')->assertSee('Salin');
        $instructions = $this->getJson('/order/ORD-ABCDEF/status')->json('instructions');
        $this->assertSame('va', $instructions['kind']);
        $this->assertSame('81234567890', $instructions['fields'][0]['value']);
        $this->assertSame(38000, $instructions['amount']);
    }

    public function test_qris_instructions_draw_the_qr_from_the_qr_string(): void
    {
        $this->pending(['payment_type' => 'qris', 'qr_string' => '00020101021226670016COM.NOBUBANK.WWW']);

        $instructions = $this->getJson('/order/ORD-ABCDEF/status')->json('instructions');

        $this->assertSame('qr', $instructions['kind']);
        $this->assertStringStartsWith('<svg', $instructions['qr']['svg']);
        $this->assertNull($instructions['qr']['image']);
    }

    public function test_gopay_uses_the_qr_image_and_deeplink_from_actions_when_no_qr_string(): void
    {
        $this->pending(['payment_type' => 'gopay', 'actions' => [
            ['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/gopay/x/qr-code'],
            ['name' => 'deeplink-redirect', 'url' => 'https://simulator.sandbox.midtrans.com/gopay/ui/checkout'],
        ]]);

        $instructions = $this->getJson('/order/ORD-ABCDEF/status')->json('instructions');

        $this->assertSame('https://api.sandbox.midtrans.com/v2/gopay/x/qr-code', $instructions['qr']['image']);
        $this->assertSame('https://simulator.sandbox.midtrans.com/gopay/ui/checkout', $instructions['deeplink']);
    }

    public function test_convenience_store_and_mandiri_bill_show_their_codes(): void
    {
        $this->pending(['payment_type' => 'cstore', 'store' => 'indomaret', 'payment_code' => 'IND123456']);
        $this->assertSame('IND123456', $this->getJson('/order/ORD-ABCDEF/status')->json('instructions.fields.0.value'));

        $this->order->payments()->update(['raw_payload' => ['payment_type' => 'echannel', 'biller_code' => '70012', 'bill_key' => '998877665544']]);
        $fields = $this->getJson('/order/ORD-ABCDEF/status')->json('instructions.fields');
        $this->assertSame(['70012', '998877665544'], array_column($fields, 'value'));
    }

    public function test_qris_without_qr_string_in_the_status_reply_points_to_our_qr_image_route(): void
    {
        // Persis bentuk balasan API status Midtrans untuk QRIS yang pending: tanpa qr_string dan tanpa actions.
        $this->pending(['payment_type' => 'qris', 'transaction_id' => 'df181272-f6df-4398-b89c-4fec11c7306a']);

        $instructions = $this->getJson('/order/ORD-ABCDEF/status')->json('instructions');

        $this->assertSame('qr', $instructions['kind']);
        $this->assertNull($instructions['qr']['svg']);
        $this->assertSame(url('/order/ORD-ABCDEF/qr'), $instructions['qr']['image']);
    }

    public function test_the_qr_route_fetches_the_image_from_midtrans_with_the_server_key_and_passes_it_on(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);
        $this->pending(['payment_type' => 'qris', 'transaction_id' => 'tx-123']);

        $this->get('/order/ORD-ABCDEF/qr')->assertOk()->assertHeader('Content-Type', 'image/png')->assertSee('PNGDATA');

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/qris/tx-123/qr-code')
            && str_starts_with($request->header('Authorization')[0], 'Basic '));
    }

    public function test_the_qr_route_is_404_without_a_pending_qr_transaction_and_after_payment(): void
    {
        $this->get('/order/ORD-ABCDEF/qr')->assertNotFound();

        $this->pending(['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bca', 'va_number' => '1']], 'transaction_id' => 'tx']);
        $this->get('/order/ORD-ABCDEF/qr')->assertNotFound();

        $this->order->payments()->update(['payment_type' => 'qris', 'raw_payload' => ['payment_type' => 'qris', 'transaction_id' => 'tx']]);
        $this->setStatus(PaymentStatus::Paid, ['paid_at' => now()]);
        $this->get('/order/ORD-ABCDEF/qr')->assertNotFound();
    }

    public function test_the_qr_route_returns_502_when_midtrans_fails(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('err', 500)]);
        $this->pending(['payment_type' => 'qris', 'transaction_id' => 'tx-123']);

        $this->get('/order/ORD-ABCDEF/qr')->assertStatus(502);
    }

    // ---- Pembayaran langsung (Core API) ------------------------------------------

    public function test_method_page_groups_options_into_dropdowns(): void
    {
        $this->get('/order/ORD-ABCDEF/bayar')->assertOk()
            ->assertSee('E-Wallet &amp; QRIS', false)->assertSee('Transfer Virtual Account')
            ->assertSee('Pilih bank')->assertSee('BCA')->assertSee('Mandiri (Bill Payment)')
            ->assertSee('Pilih e-wallet')->assertSee('GoPay')->assertSee('ShopeePay')
            ->assertSee('role="listbox"', false)->assertSee('Permata')->assertSee('CIMB Niaga')
            ->assertDontSee('Minimarket')->assertDontSee('Indomaret')->assertDontSee('Alfamart')
            ->assertDontSee('Kartu Kredit')->assertDontSee('3D Secure');

        // Minimarket dan kartu sudah tidak ditawarkan: server menolaknya juga.
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'indomaret'])->assertUnprocessable();
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'card'])->assertUnprocessable();
        $this->assertSame([], $this->gateway->charges);
    }

    public function test_choosing_a_bank_creates_the_virtual_account_directly_without_a_popup(): void
    {
        $this->gateway->chargeReply = ['va_numbers' => [['bank' => 'bca', 'va_number' => '81234567890']]];

        $response = $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bca_va'])->assertOk();

        $response->assertJsonPath('mode', 'charge')->assertJsonPath('snap_token', null);
        $this->assertSame([], $this->gateway->tokenRequests, 'tanpa popup Snap');
        $this->assertSame('bank_transfer', $this->gateway->charges[0]['spec']['payment_type']);
        $this->assertSame(['bank' => 'bca'], $this->gateway->charges[0]['spec']['bank_transfer']);
        $this->assertDatabaseHas('payments', ['order_id' => $this->order->id, 'method' => 'bca_va', 'transaction_status' => 'pending', 'payment_type' => 'bank_transfer']);

        // Nomor VA langsung tersedia untuk halaman hasil, tanpa menunggu webhook.
        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()->assertSee('81234567890')->assertSee('Virtual Account BCA');
    }

    public function test_each_option_sends_the_right_charge_parameters(): void
    {
        $expected = [
            'qris' => ['qris', ['acquirer' => 'gopay']],
            'gopay' => ['gopay', null],
            'shopeepay' => ['shopeepay', null],
            'bni_va' => ['bank_transfer', ['bank' => 'bni']],
            'permata_va' => ['bank_transfer', ['bank' => 'permata']],
            'mandiri_bill' => ['echannel', null],
        ];

        foreach ($expected as $method => [$type, $block]) {
            $this->order->payments()->delete();
            $this->order->forceFill(['expires_at' => null])->save();
            $this->gateway->charges = [];

            $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => $method])->assertOk()->assertJsonPath('mode', 'charge');

            $spec = $this->gateway->charges[0]['spec'];
            $this->assertSame($type, $spec['payment_type'], $method);
            if ($block !== null) {
                $this->assertSame($block, $spec[$type], $method);
            }
        }
    }

    public function test_choosing_the_same_method_again_returns_to_the_same_transaction(): void
    {
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bca_va'])->assertOk();
        $this->gateway->statuses['ORD-ABCDEF-1'] = ['order_id' => 'ORD-ABCDEF-1', 'status_code' => '201', 'gross_amount' => '38000.00', 'transaction_status' => 'pending', 'payment_type' => 'bank_transfer'];

        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bca_va'])->assertOk();

        $this->assertCount(1, $this->gateway->charges, 'nomor VA yang sama, tidak dibuat ulang');
        $this->assertSame([], $this->gateway->cancelled);
        $this->assertSame(1, $this->order->payments()->count());
    }

    public function test_switching_bank_cancels_the_old_virtual_account_and_creates_a_new_one(): void
    {
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bca_va'])->assertOk();
        $this->gateway->statuses['ORD-ABCDEF-1'] = ['order_id' => 'ORD-ABCDEF-1', 'status_code' => '201', 'gross_amount' => '38000.00', 'transaction_status' => 'pending', 'payment_type' => 'bank_transfer'];

        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bni_va'])->assertOk();

        $this->assertSame(['ORD-ABCDEF-1'], $this->gateway->cancelled);
        $payments = $this->order->payments()->orderBy('id')->get();
        $this->assertSame('cancel', $payments[0]->transaction_status);
        $this->assertSame('bni_va', $payments[1]->method);
        $this->assertSame('ORD-ABCDEF-2', $payments[1]->midtrans_order_id);
    }

    public function test_when_midtrans_rejects_the_method_the_customer_gets_a_clear_message_and_no_clock_starts(): void
    {
        $this->order->forceFill(['expires_at' => null])->save();
        $this->gateway->failCharge = true;

        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bca_va'])->assertStatus(502)
            ->assertJsonPath('message', 'Metode pembayaran ini belum bisa dipakai saat ini. Coba metode lain, atau ulangi sebentar lagi.');

        $this->assertNull($this->order->fresh()->expires_at);
    }

    public function test_the_pending_notification_does_not_wipe_the_va_number_from_the_charge_reply(): void
    {
        $this->gateway->chargeReply = ['va_numbers' => [['bank' => 'bca', 'va_number' => '81234567890']]];
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bca_va'])->assertOk();

        // Notifikasi pending yang lebih ringkas (tanpa va_numbers) tidak boleh menghapus data tadi.
        $payload = ['order_id' => 'ORD-ABCDEF-1', 'status_code' => '201', 'gross_amount' => '38000.00', 'transaction_status' => 'pending', 'payment_type' => 'bank_transfer'];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('midtrans.server_key'));
        $this->postJson('/payments/midtrans/notification', $payload)->assertOk();

        $this->assertSame('81234567890', $this->order->payments()->first()->raw_payload['va_numbers'][0]['va_number']);
    }

    // ---- Hitung mundur -----------------------------------------------------------

    public function test_the_clock_starts_when_pay_is_pressed_not_before(): void
    {
        config(['midtrans.expiry_minutes' => 5]);
        $this->order->forceFill(['expires_at' => null])->save();

        $this->assertNull($this->getJson('/order/ORD-ABCDEF/status')->json('expiresAt'));
        $this->get('/order/ORD-ABCDEF/bayar')->assertOk()->assertDontSee('Waktu mulai berjalan', false)->assertDontSee('Batas waktu bayar', false);

        $response = $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertOk();

        $this->assertEqualsWithDelta(5 * 60, now()->diffInSeconds($this->order->fresh()->expires_at, true), 3);
        $this->assertSame(5, $this->gateway->charges[0]['expiryMinutes']);
        $this->assertSame($this->order->fresh()->expires_at->toIso8601String(), $response->json('expires_at'));
        $this->assertNotNull($response->json('server_time'));
        $this->assertSame($response->json('expires_at'), $this->getJson('/order/ORD-ABCDEF/status')->json('expiresAt'));
    }

    public function test_there_are_no_instructions_before_midtrans_records_the_transaction_or_after_payment(): void
    {
        // Percobaan sudah dibuat tapi Midtrans belum mencatat apa pun.
        $this->order->payments()->create(['midtrans_order_id' => 'ORD-ABCDEF-1', 'gross_amount' => 38000, 'method' => 'bca_va']);
        $this->assertNull($this->getJson('/order/ORD-ABCDEF/status')->json('instructions'), 'belum ada kabar dari Midtrans');

        $this->order->payments()->update(['transaction_status' => 'pending', 'raw_payload' => ['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bni', 'va_number' => '999']]]]);
        $this->assertNotNull($this->getJson('/order/ORD-ABCDEF/status')->json('instructions'));

        $this->setStatus(PaymentStatus::Paid, ['paid_at' => now()]);
        $this->assertNull($this->getJson('/order/ORD-ABCDEF/status')->json('instructions'), 'sudah lunas');
    }

    public function test_waiting_page_without_instructions_sends_the_customer_to_pick_a_method_and_never_loads_snap(): void
    {
        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()
            ->assertSee('Pilih Metode Pembayaran')->assertSee('/order/ORD-ABCDEF/bayar?ganti=1', false)
            ->assertDontSee('snap.js', false)->assertDontSee('resume()', false);
    }

    public function test_card_is_not_offered_and_nothing_loads_the_snap_popup(): void
    {
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'card'])->assertUnprocessable();

        foreach (['/order/ORD-ABCDEF', '/order/ORD-ABCDEF/bayar', '/order/ORD-ABCDEF/hasil', '/keranjang'] as $url) {
            $this->withSession(['table_id' => $this->order->table_id])->get($url)->assertDontSee('snap.js', false);
        }
    }

    public function test_the_method_page_opens_normally_unless_a_payment_with_instructions_is_running(): void
    {
        $this->get('/order/ORD-ABCDEF/bayar')->assertOk();

        // Transaksi tercatat tapi tanpa data bayar yang bisa ditampilkan: halaman tidak diblokir.
        $this->order->payments()->create(['midtrans_order_id' => 'ORD-ABCDEF-1', 'gross_amount' => 38000, 'method' => 'bca_va', 'transaction_status' => 'pending', 'raw_payload' => ['payment_type' => 'credit_card']]);
        $this->get('/order/ORD-ABCDEF/bayar')->assertOk();
    }

    public function test_switching_method_does_not_restart_the_clock(): void
    {
        config(['midtrans.expiry_minutes' => 5]);
        $this->order->forceFill(['expires_at' => null])->save();

        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'qris'])->assertOk();
        $deadline = $this->order->fresh()->expires_at;

        $this->travel(2)->minutes();
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bni_va'])->assertOk();

        $this->assertTrue($deadline->equalTo($this->order->fresh()->expires_at), 'batas waktu tetap sama');
        $this->assertSame(3, $this->gateway->charges[1]['expiryMinutes'], 'transaksi baru hanya berlaku sampai sisa waktu');
    }

    // ---- Tombol Kembali ----------------------------------------------------------

    public function test_back_to_the_method_page_bounces_to_the_payment_page_while_a_payment_is_running(): void
    {
        $this->gateway->chargeReply = ['va_numbers' => [['bank' => 'bca', 'va_number' => '81234567890']]];
        $this->postJson('/order/ORD-ABCDEF/bayar', ['method' => 'bca_va'])->assertOk();

        // Tombol Kembali browser membuka /bayar polos: dilempar balik ke halaman pembayaran.
        $this->get('/order/ORD-ABCDEF/bayar')->assertRedirect('/order/ORD-ABCDEF/hasil');

        // Ganti metode hanya lewat tombolnya (?ganti=1).
        $this->get('/order/ORD-ABCDEF/bayar?ganti=1')->assertOk()->assertSee('Pilih Metode Pembayaran');
    }

    public function test_the_change_method_buttons_are_prominent_and_carry_the_flag(): void
    {
        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()
            ->assertSee('/order/ORD-ABCDEF/bayar?ganti=1', false)->assertSee('Ganti Metode Pembayaran')->assertSee('border-brand-600', false);
    }

    public function test_customer_pages_have_a_footer_with_the_current_year_and_company_name(): void
    {
        $this->travelTo(now()->setYear(2031));

        $this->get('/order/ORD-ABCDEF/hasil')->assertOk()->assertSee('&copy; 2031 PT. '.config('app.name').'. All Rights Reserved', false);
        $this->get('/order/ORD-ABCDEF/bayar')->assertOk()->assertSee('All Rights Reserved');
    }

    public function test_cart_lists_the_same_payment_methods_as_the_method_page(): void
    {
        $this->withSession(['table_id' => $this->order->table_id])->get('/keranjang')->assertOk()
            ->assertSee('Pilih metodenya di langkah berikutnya')
            ->assertSee('E-Wallet &amp; QRIS', false)->assertSee('QRIS, GoPay, ShopeePay')
            ->assertSee('Transfer Virtual Account')->assertSee('BCA, BNI, BRI, Permata, CIMB Niaga, Mandiri')
            ->assertDontSee('Kamu memilih metodenya');
    }
}
