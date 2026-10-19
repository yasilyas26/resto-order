<?php

namespace Tests\Feature\Visitor;

use App\Models\Category;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    private function visit(array $f): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['table_id' => $f['table']->id])->get('/menu');
    }

    public function test_menu_is_grouped_by_category_with_prices(): void
    {
        $f = $this->menuFixture();
        $drinks = Category::create(['name' => 'Minuman', 'sort' => 5]);
        Menu::create(['category_id' => $drinks->id, 'name' => 'Es Jeruk', 'price' => 7000, 'is_available' => true]);

        $this->visit($f)->assertOk()
            ->assertSeeInOrder(['Mie', 'Mie Pedas', 'Rp 15.000', 'Minuman', 'Es Jeruk', 'Rp 7.000'])
            ->assertSee('Meja A07');
    }

    public function test_sold_out_menu_is_shown_grayed_out_without_an_add_button(): void
    {
        $f = $this->menuFixture();
        $f['esTeh']->update(['is_available' => false]);

        $html = $this->visit($f)->assertSee('Stok Habis')->getContent();

        $this->assertStringContainsString('Pilih Mie Pedas', $html, 'menu yang tersedia punya tombol +');
        $this->assertStringNotContainsString('Pilih Es Teh', $html, 'menu habis tidak punya tombol +');
    }

    public function test_deleted_menus_and_empty_categories_are_hidden(): void
    {
        $f = $this->menuFixture();
        $f['esTeh']->delete();
        Category::create(['name' => 'Kategori Kosong']);

        $this->visit($f)->assertDontSee('Es Teh')->assertDontSee('Kategori Kosong');
    }

    public function test_option_data_for_the_modal_reaches_the_page_without_deleted_options(): void
    {
        $f = $this->menuFixture();
        $f['lv3']->delete();

        $html = $this->visit($f)->getContent();

        $this->assertStringContainsString('Level pedas', $html);
        $this->assertStringContainsString('Level 0', $html);
        $this->assertStringNotContainsString('Level 3', $html);
    }

    public function test_cart_page_leads_to_the_payment_method_page_and_does_not_load_snap(): void
    {
        $f = $this->menuFixture();
        config(['midtrans.client_key' => 'SB-Mid-client-test']);

        $this->withSession(['table_id' => $f['table']->id])->get('/keranjang')
            ->assertOk()
            ->assertSee('Keranjang Pesanan (Meja A07)')
            ->assertSee('Lanjut ke Pembayaran')
            ->assertDontSee('snap.js', false) // popup Snap dibuka dari halaman pilih metode, bukan dari keranjang
            ->assertDontSee('SB-Mid-server', false);
    }

    public function test_the_server_key_never_reaches_the_browser(): void
    {
        $f = $this->menuFixture();
        config(['midtrans.client_key' => 'client-abc', 'midtrans.server_key' => 'SERVER-SECRET-123']);

        $this->withSession(['table_id' => $f['table']->id])->get('/keranjang')->assertDontSee('SERVER-SECRET-123');
    }

    public function test_pricing_shows_no_separate_tax_or_service_fee(): void
    {
        $f = $this->menuFixture();

        $this->visit($f)->assertSee('Harga sudah termasuk pajak')->assertDontSee('PB1')->assertDontSee('Biaya Layanan');
        $this->withSession(['table_id' => $f['table']->id])->get('/keranjang')->assertDontSee('PB1')->assertDontSee('Biaya Layanan');
    }
}
