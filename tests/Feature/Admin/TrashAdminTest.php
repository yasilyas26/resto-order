<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesFakeImages;
use Tests\TestCase;

class TrashAdminTest extends TestCase
{
    use MakesFakeImages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    private function menu(?Category $category = null, ?string $image = null): Menu
    {
        $category ??= Category::create(['name' => 'Makanan']);

        return Menu::create([
            'category_id' => $category->id,
            'name' => 'Mie Pedas',
            'price' => 15000,
            'image' => $image,
        ]);
    }

    public function test_trash_page_lists_deleted_items_of_every_kind(): void
    {
        $category = Category::create(['name' => 'Minuman']);
        $menu = $this->menu();
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $option = $group->options()->create(['name' => 'Keju', 'extra_price' => 2000]);
        $table = Table::create(['number' => 'B02']);
        foreach ([$category, $menu, $option, $table, $group] as $item) {
            $item->delete();
        }

        $this->get('/admin/trash')->assertOk()
            ->assertSee('Minuman')->assertSee('Mie Pedas')
            ->assertSee('Topping › Keju', false)->assertSee('Meja B02');
    }

    public function test_deleted_item_can_be_restored(): void
    {
        $category = Category::create(['name' => 'Minuman']);
        $category->delete();

        $this->post("/admin/trash/categories/{$category->id}/restore")->assertSessionHas('status');

        $this->assertNotSoftDeleted($category);
    }

    public function test_unknown_type_is_404(): void
    {
        $this->post('/admin/trash/orders/1/restore')->assertNotFound();
        $this->delete('/admin/trash/users/1')->assertNotFound();
    }

    public function test_active_items_cannot_go_through_the_trash_endpoints(): void
    {
        $category = Category::create(['name' => 'Minuman']);

        $this->delete("/admin/trash/categories/{$category->id}")->assertNotFound();

        $this->assertNotSoftDeleted($category);
    }

    // ---- Kategori -----------------------------------------------------------

    public function test_category_cannot_be_restored_when_its_name_is_taken(): void
    {
        $old = Category::create(['name' => 'Minuman']);
        $old->delete();
        Category::create(['name' => 'Minuman']);

        $this->post("/admin/trash/categories/{$old->id}/restore")->assertSessionHas('error');

        $this->assertSoftDeleted($old);
    }

    public function test_category_with_trashed_menus_cannot_be_force_deleted(): void
    {
        $category = Category::create(['name' => 'Makanan']);
        $this->menu($category)->delete();
        $category->delete();

        $this->delete("/admin/trash/categories/{$category->id}")->assertSessionHas('error');

        $this->assertSoftDeleted($category);
    }

    public function test_empty_category_can_be_force_deleted(): void
    {
        $category = Category::create(['name' => 'Minuman']);
        $category->delete();

        $this->delete("/admin/trash/categories/{$category->id}")->assertSessionHas('status');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // ---- Menu ---------------------------------------------------------------

    public function test_menu_cannot_be_restored_while_its_category_is_deleted(): void
    {
        $category = Category::create(['name' => 'Makanan']);
        $menu = $this->menu($category);
        $menu->delete();
        $category->delete();

        $this->post("/admin/trash/menus/{$menu->id}/restore")->assertSessionHas('error');
        $this->assertSoftDeleted($menu);

        $this->post("/admin/trash/categories/{$category->id}/restore");
        $this->post("/admin/trash/menus/{$menu->id}/restore")->assertSessionHas('status');
        $this->assertNotSoftDeleted($menu);
    }

    public function test_force_deleting_a_menu_removes_its_photo_file(): void
    {
        $path = $this->fakeImage('a.jpg')->store('menus', 'public');
        $menu = $this->menu(image: $path);
        $menu->delete();
        Storage::disk('public')->assertExists($path);

        $this->delete("/admin/trash/menus/{$menu->id}")->assertSessionHas('status');

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_force_deleting_a_menu_also_detaches_its_option_groups(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $menu = $this->menu();
        $menu->optionGroups()->attach($group->id, ['sort' => 0]);
        $menu->delete();

        $this->delete("/admin/trash/menus/{$menu->id}")->assertSessionHas('status');

        $this->assertDatabaseCount('menu_option_group', 0);
        $this->assertNotNull(OptionGroup::find($group->id), 'grup opsi tidak ikut terhapus');
    }

    // ---- Grup opsi dan opsi ---------------------------------------------------

    public function test_group_attached_to_a_menu_cannot_be_force_deleted(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $this->menu()->optionGroups()->attach($group->id, ['sort' => 0]);
        $group->delete();

        $this->delete("/admin/trash/option-groups/{$group->id}")->assertSessionHas('error');

        $this->assertSoftDeleted($group);
    }

    public function test_unattached_group_can_be_force_deleted_together_with_its_options(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $group->options()->create(['name' => 'Keju', 'extra_price' => 2000]);
        $group->delete();

        $this->delete("/admin/trash/option-groups/{$group->id}")->assertSessionHas('status');

        $this->assertDatabaseMissing('option_groups', ['id' => $group->id]);
        $this->assertDatabaseCount('options', 0);
    }

    public function test_option_cannot_be_restored_while_its_group_is_deleted(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $option = $group->options()->create(['name' => 'Keju', 'extra_price' => 2000]);
        $option->delete();
        $group->delete();

        $this->post("/admin/trash/options/{$option->id}/restore")->assertSessionHas('error');
        $this->assertSoftDeleted($option);

        $this->post("/admin/trash/option-groups/{$group->id}/restore");
        $this->post("/admin/trash/options/{$option->id}/restore")->assertSessionHas('status');
        $this->assertNotSoftDeleted($option);
    }

    public function test_option_of_an_attached_group_cannot_be_force_deleted(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $option = $group->options()->create(['name' => 'Keju', 'extra_price' => 2000]);
        $this->menu()->optionGroups()->attach($group->id, ['sort' => 0]);
        $option->delete();

        $this->delete("/admin/trash/options/{$option->id}")->assertSessionHas('error');

        $this->assertSoftDeleted($option);
    }

    public function test_unattached_option_can_be_force_deleted(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $option = $group->options()->create(['name' => 'Keju', 'extra_price' => 2000]);
        $option->delete();

        $this->delete("/admin/trash/options/{$option->id}")->assertSessionHas('status');

        $this->assertDatabaseMissing('options', ['id' => $option->id]);
    }

    // ---- Meja -----------------------------------------------------------------

    public function test_table_cannot_be_restored_when_its_number_is_taken(): void
    {
        $old = Table::create(['number' => 'A07']);
        $old->delete();
        Table::create(['number' => 'A07']);

        $this->post("/admin/trash/tables/{$old->id}/restore")->assertSessionHas('error');

        $this->assertSoftDeleted($old);
    }

    public function test_restored_table_gets_its_old_code_back(): void
    {
        $table = Table::create(['number' => 'A07']);
        $table->code = Table::generateCode('A07');
        $table->save();
        $code = $table->code;
        $table->delete();

        $this->post("/admin/trash/tables/{$table->id}/restore")->assertSessionHas('status');

        $this->assertSame($code, $table->fresh()->code);
    }

    public function test_table_without_orders_can_be_force_deleted(): void
    {
        $table = Table::create(['number' => 'A07']);
        $table->code = Table::generateCode('A07');
        $table->save();
        $table->delete();

        $this->delete("/admin/trash/tables/{$table->id}")->assertSessionHas('status');

        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
    }
}
