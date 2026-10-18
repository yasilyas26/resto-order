<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Menu;
use App\Models\OptionGroup;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesFakeImages;
use Tests\TestCase;

/** Halaman yang berisi data harus tampil utuh (bukan cuma halaman kosong). */
class AdminPagesRenderTest extends TestCase
{
    use MakesFakeImages;
    use RefreshDatabase;

    public function test_pages_render_with_real_data(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        $category = Category::create(['name' => 'Makanan', 'sort' => 1]);
        $group = OptionGroup::create(['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);
        $group->options()->create(['name' => 'Lv 0', 'extra_price' => 0, 'sort' => 0, 'is_available' => true]);
        $group->options()->create(['name' => 'Lv 4', 'extra_price' => 1000, 'sort' => 4, 'is_available' => true]);
        $menu = Menu::create([
            'category_id' => $category->id,
            'name' => 'Mie Pedas',
            'description' => 'Enak',
            'price' => 15000,
            'image' => $this->fakeImage('mie.png')->store('menus', 'public'),
        ]);
        $menu->optionGroups()->attach($group->id, ['sort' => 0]);
        $table = Table::create(['number' => 'A07']);

        $this->get('/admin/categories')->assertOk()->assertSee('Makanan');
        $this->get("/admin/categories/{$category->id}/edit")->assertOk()->assertSee('Makanan');

        $this->get('/admin/menus')->assertOk()
            ->assertSee('Mie Pedas')->assertSee('Rp 15.000')->assertSee('Level pedas')->assertSee('Tersedia');
        $this->get('/admin/menus/create')->assertOk()->assertSee('Level pedas');
        $this->get("/admin/menus/{$menu->id}/edit")->assertOk()
            ->assertSee('Mie Pedas')->assertSee('Hapus foto ini')->assertSee('Lv 0, Lv 4');

        $this->get('/admin/option-groups')->assertOk()->assertSee('Level pedas')->assertSee('Wajib');
        $this->get("/admin/option-groups/{$group->id}/edit")->assertOk()->assertSee('Lv 4')->assertSee('+ Tambah');

        $this->get('/admin/tables')->assertOk()->assertSee('A07')->assertSee($table->code);
        $this->get("/admin/tables/{$table->id}/edit")->assertOk()->assertSee($table->code);
    }

    public function test_menu_edit_form_preselects_category_and_groups(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        $category = Category::create(['name' => 'Makanan']);
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 3]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Nasi', 'price' => 12000]);
        $menu->optionGroups()->attach($group->id, ['sort' => 0]);

        $html = $this->get("/admin/menus/{$menu->id}/edit")->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$category->id.'"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="'.$group->id.'"[^>]*checked/s', $html);
    }

    public function test_validation_errors_keep_the_typed_input(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $category = Category::create(['name' => 'Makanan']);

        $this->from('/admin/menus/create')
            ->post('/admin/menus', ['category_id' => $category->id, 'name' => 'Mie Gagal', 'price' => 'abc'])
            ->assertRedirect('/admin/menus/create');

        $this->get('/admin/menus/create')->assertSee('Mie Gagal');
    }
}
