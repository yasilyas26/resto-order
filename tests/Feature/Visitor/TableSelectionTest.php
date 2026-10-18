<?php

namespace Tests\Feature\Visitor;

use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\TestCase;

class TableSelectionTest extends TestCase
{
    use BuildsMenu;
    use RefreshDatabase;

    public function test_onboarding_page_shows_scan_and_manual_code(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Scan QR dengan Kamera')
            ->assertSee('Kode Meja / Table ID');
    }

    public function test_scanning_a_valid_qr_stores_the_table_and_opens_the_menu(): void
    {
        $f = $this->menuFixture();

        $this->get('/m/'.$f['table']->code)
            ->assertRedirect('/menu')
            ->assertSessionHas('table_id', $f['table']->id);
    }

    public function test_the_code_is_matched_case_insensitively(): void
    {
        $f = $this->menuFixture();

        $this->get('/m/'.strtolower($f['table']->code))->assertRedirect('/menu');
    }

    public function test_unknown_code_stays_on_onboarding_with_an_error(): void
    {
        $this->get('/m/NOPE-0000')
            ->assertRedirect('/')
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('table_id');
    }

    public function test_inactive_and_deleted_tables_cannot_be_selected(): void
    {
        $f = $this->menuFixture();
        $f['table']->update(['is_active' => false]);
        $this->get('/m/'.$f['table']->code)->assertSessionHasErrors('code');

        $f['table']->update(['is_active' => true]);
        $f['table']->delete();
        $this->get('/m/'.$f['table']->code)->assertSessionHasErrors('code');
    }

    public function test_manual_code_entry_works_and_is_forgiving_about_spaces_and_case(): void
    {
        $f = $this->menuFixture();

        $this->post('/meja', ['code' => '  '.strtolower($f['table']->code).' '])
            ->assertRedirect('/menu')
            ->assertSessionHas('table_id', $f['table']->id);
    }

    public function test_wrong_manual_code_keeps_what_was_typed(): void
    {
        $this->from('/')->post('/meja', ['code' => 'SALAH-1'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('code')
            ->assertSessionHasInput('code', 'SALAH-1');

        $this->post('/meja', [])->assertSessionHasErrors('code');
    }

    public function test_guessing_codes_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/meja', ['code' => 'TEBAK-'.$i])->assertRedirect('/');
        }

        $this->post('/meja', ['code' => 'TEBAK-X'])->assertStatus(429);
    }

    public function test_menu_and_cart_need_a_selected_table(): void
    {
        $this->get('/menu')->assertRedirect('/')->assertSessionHas('error');
        $this->get('/keranjang')->assertRedirect('/');
    }

    public function test_a_table_deactivated_by_admin_stops_working_immediately(): void
    {
        $f = $this->menuFixture();
        $this->withSession(['table_id' => $f['table']->id])->get('/menu')->assertOk();

        $f['table']->update(['is_active' => false]);

        $this->withSession(['table_id' => $f['table']->id])->get('/menu')->assertRedirect('/');
    }

    public function test_onboarding_skips_straight_to_the_menu_when_a_table_is_already_chosen(): void
    {
        $f = $this->menuFixture();

        $this->withSession(['table_id' => $f['table']->id])->get('/')->assertRedirect('/menu');
    }

    public function test_change_table_forgets_the_choice(): void
    {
        $f = $this->menuFixture();

        $this->withSession(['table_id' => $f['table']->id])
            ->get('/ganti-meja')
            ->assertRedirect('/')
            ->assertSessionMissing('table_id');
    }

    public function test_the_header_shows_the_current_table_with_a_change_link(): void
    {
        $f = $this->menuFixture();

        $this->withSession(['table_id' => $f['table']->id])->get('/menu')
            ->assertSee('Meja A07')
            ->assertSee('/ganti-meja', false);
    }

    /** PRD G-13: tidak ada link ke /admin atau /staff di halaman mana pun untuk pengunjung. */
    public function test_visitor_pages_never_link_to_admin_or_staff_areas(): void
    {
        $f = $this->menuFixture();
        $order = \App\Models\Order::create([
            'code' => 'ORD-AAAAAA', 'table_id' => $f['table']->id, 'customer_name' => 'Budi',
            'order_type' => 'dine_in', 'subtotal' => 5000, 'total' => 5000,
        ]);
        $withTable = ['table_id' => $f['table']->id];

        $pages = [
            $this->get('/'),
            $this->get('/login'),
            $this->get('/register'),
            $this->get('/riwayat'),
            $this->get('/order/'.$order->code),
            $this->withSession($withTable)->get('/menu'),
            $this->withSession($withTable)->get('/keranjang'),
        ];

        foreach ($pages as $response) {
            $response->assertOk();
            $html = $response->getContent();
            $this->assertStringNotContainsString('/admin', $html);
            $this->assertStringNotContainsString('/staff', $html);
        }
    }

    public function test_a_logged_in_admin_also_sees_no_admin_link_on_visitor_pages(): void
    {
        $f = $this->menuFixture();
        $this->actingAs(User::factory()->admin()->create());

        $html = $this->withSession(['table_id' => $f['table']->id])->get('/menu')->getContent();

        $this->assertStringNotContainsString('/admin', $html);
        $this->assertStringNotContainsString('/staff', $html);
    }

    public function test_qr_url_generated_for_a_table_matches_the_scan_route(): void
    {
        $table = Table::create(['number' => 'B01']);

        $this->get(parse_url($table->scanUrl(), PHP_URL_PATH))->assertRedirect('/menu');
    }
}
