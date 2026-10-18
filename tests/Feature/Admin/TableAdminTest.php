<?php

namespace Tests\Feature\Admin;

use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableAdminTest extends TestCase
{
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
}
