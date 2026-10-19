<?php

namespace Tests\Feature\Admin;

use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMenu;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class TableAdminTest extends TestCase
{
    use BuildsMenu;
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_creating_a_table_generates_a_random_readable_code(): void
    {
        $this->post('/admin/tables', ['number' => 'A07', 'is_active' => '1'])
            ->assertRedirect('/admin/tables');

        $table = Table::firstOrFail();
        $this->assertMatchesRegularExpression('/^A07-[A-HJ-NP-Z2-9]{4}$/', $table->code);
        $this->assertTrue($table->is_active);
    }

    public function test_code_cannot_be_chosen_by_the_client(): void
    {
        $this->post('/admin/tables', ['number' => 'A07', 'code' => 'HACK-0000']);

        $this->assertNotSame('HACK-0000', Table::firstOrFail()->code);
    }

    public function test_codes_are_unique_per_table(): void
    {
        $this->post('/admin/tables', ['number' => 'A07']);
        $this->post('/admin/tables', ['number' => 'A08']);

        $this->assertSame(2, Table::pluck('code')->unique()->count());
    }

    public function test_duplicate_number_is_rejected_but_deleted_numbers_can_be_reused(): void
    {
        $this->post('/admin/tables', ['number' => 'A07']);
        $this->post('/admin/tables', ['number' => 'A07'])->assertSessionHasErrors('number');

        Table::firstOrFail()->delete();
        $this->post('/admin/tables', ['number' => 'A07'])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Table::count());
        $this->assertSame(2, Table::withTrashed()->count());
    }

    public function test_number_format_is_validated(): void
    {
        $this->post('/admin/tables', ['number' => 'A 07!'])->assertSessionHasErrors('number');
        $this->post('/admin/tables', ['number' => ''])->assertSessionHasErrors('number');
    }

    public function test_table_can_be_deactivated_and_keeps_its_code(): void
    {
        $this->post('/admin/tables', ['number' => 'A07', 'is_active' => '1']);
        $table = Table::firstOrFail();
        $code = $table->code;

        $this->put("/admin/tables/{$table->id}", ['number' => 'A07', 'is_active' => '0'])
            ->assertSessionDoesntHaveErrors();

        $table->refresh();
        $this->assertFalse($table->is_active);
        $this->assertSame($code, $table->code);
    }

    public function test_regenerate_changes_the_code(): void
    {
        $this->post('/admin/tables', ['number' => 'A07']);
        $table = Table::firstOrFail();
        $old = $table->code;

        $this->post("/admin/tables/{$table->id}/regenerate")->assertRedirect('/admin/tables');

        $this->assertNotSame($old, $table->fresh()->code);
        $this->assertStringStartsWith('A07-', $table->fresh()->code);
    }

    public function test_qr_page_shows_the_code_and_an_svg_with_the_scan_url(): void
    {
        $this->post('/admin/tables', ['number' => 'A07']);
        $table = Table::firstOrFail();

        $this->get("/admin/tables/{$table->id}/qr")
            ->assertOk()
            ->assertSee($table->code)
            ->assertSee('<svg', false)
            ->assertSee(url('/m/'.$table->code));
    }

    public function test_qr_download_is_an_svg_file(): void
    {
        $this->post('/admin/tables', ['number' => 'A07']);
        $table = Table::firstOrFail();

        $response = $this->get("/admin/tables/{$table->id}/qr/download");

        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_deleting_a_table_is_soft_and_its_qr_page_is_gone(): void
    {
        $this->post('/admin/tables', ['number' => 'A07']);
        $table = Table::firstOrFail();

        $this->delete("/admin/tables/{$table->id}")->assertRedirect('/admin/tables');

        $this->assertSoftDeleted($table);
        $this->get("/admin/tables/{$table->id}/qr")->assertNotFound();
    }

    public function test_a_deleted_tables_code_is_never_reused(): void
    {
        $this->post('/admin/tables', ['number' => 'A07']);
        $table = Table::firstOrFail();
        $table->delete();

        $codes = collect(range(1, 30))->map(fn () => Table::generateCode('A07'));

        $this->assertFalse($codes->contains($table->code));
    }

    public function test_the_table_seeder_creates_forty_tables_and_is_safe_to_run_again(): void
    {
        Table::create(['number' => '1']); // sudah ada: tidak digandakan
        $trashed = Table::create(['number' => '7']);
        $trashed->delete(); // ada di Sampah: tidak dibuat ulang

        $this->seed(\Database\Seeders\TableSeeder::class);
        $this->seed(\Database\Seeders\TableSeeder::class);

        $this->assertSame(40, Table::withTrashed()->count());
        $this->assertSame(39, Table::count(), 'meja 7 tetap di Sampah');
        $this->assertSame(40, Table::withTrashed()->distinct()->count('code'), 'tiap meja punya kode unik');
    }

    // ---- Urutkan -----------------------------------------------------------------

    /** @return list<string> nomor meja di halaman yang ditampilkan, sesuai urutan. */
    private function numbersOn(string $query): array
    {
        return collect($this->get('/admin/tables?'.$query)->assertOk()->viewData('tables')->items())->pluck('number')->all();
    }

    public function test_tables_are_sorted_by_number_as_a_number_by_default_not_as_text(): void
    {
        foreach (['10', '9', '2', '1', '11'] as $n) {
            Table::create(['number' => $n]);
        }

        // Diurut sebagai teks hasilnya 1, 10, 11, 2, 9 (salah untuk dibaca manusia).
        $this->assertSame(['1', '2', '9', '10', '11'], $this->numbersOn(''));
        $this->assertSame(['11', '10', '9', '2', '1'], $this->numbersOn('sort=number_desc'));
    }

    public function test_tables_can_be_sorted_by_when_they_were_added(): void
    {
        foreach (['5', '3', '8'] as $n) {
            Table::create(['number' => $n]);
        }

        $this->assertSame(['8', '3', '5'], $this->numbersOn('sort=newest'));
        $this->assertSame(['5', '3', '8'], $this->numbersOn('sort=oldest'));
    }

    public function test_active_and_inactive_tables_can_be_listed_first_keeping_number_order_inside_each_group(): void
    {
        Table::create(['number' => '1']);
        Table::create(['number' => '2', 'is_active' => false]);
        Table::create(['number' => '3']);
        Table::create(['number' => '4', 'is_active' => false]);

        $this->assertSame(['1', '3', '2', '4'], $this->numbersOn('sort=active'));
        $this->assertSame(['2', '4', '1', '3'], $this->numbersOn('sort=inactive'));
    }

    public function test_occupied_tables_come_first_when_asked(): void
    {
        $f = $this->menuFixture(); // meja fixture "A07"
        foreach (['1', '2', '3'] as $n) {
            Table::create(['number' => $n]);
        }
        $busy = Table::where('number', '3')->firstOrFail();
        $this->paidOrder($f, ['table_id' => $busy->id]);

        $this->assertSame('3', $this->numbersOn('sort=occupied')[0] ?? null);
        $this->assertSame(['1', '2', '3', 'A07'], $this->numbersOn(''), 'urutan bawaan: angka dulu, nomor huruf di belakang');
        $this->assertSame(['3', '2', '1', 'A07'], $this->numbersOn('sort=number_desc'));
    }

    public function test_an_unknown_sort_falls_back_to_the_default_and_the_dropdown_lists_every_option(): void
    {
        foreach (['2', '1'] as $n) {
            Table::create(['number' => $n]);
        }

        $this->assertSame(['1', '2'], $this->numbersOn('sort=ngawur'));

        $this->get('/admin/tables?sort=newest')->assertOk()
            ->assertSee('Urutkan')->assertSee('Nomor: kecil ke besar')->assertSee('Terbaru ditambahkan')->assertSee('Terisi dulu')->assertSee('Nonaktif dulu')
            ->assertSee('<option value="newest" selected>', false);
    }

    public function test_sorting_keeps_the_search_term_and_stays_consistent_across_pages(): void
    {
        foreach (range(1, 20) as $n) {
            Table::create(['number' => (string) $n]);
        }

        $this->get('/admin/tables?q=1&sort=number_desc')->assertOk()
            ->assertSee('value="1"', false)->assertSee('sort=number_desc', false);

        $page1 = $this->numbersOn('sort=number_desc&page=1');
        $page2 = $this->numbersOn('sort=number_desc&page=2');
        $this->assertSame(['20', '19', '18', '17', '16', '15', '14', '13'], $page1);
        $this->assertSame(['12', '11', '10', '9', '8', '7', '6', '5'], $page2);
    }
}
