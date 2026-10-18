<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesFakeImages;
use Tests\TestCase;

class MenuAdminTest extends TestCase
{
    use MakesFakeImages;
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $this->category = Category::create(['name' => 'Makanan']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name' => 'Mie Pedas',
            'description' => 'Mie dengan level pedas pilihan.',
            'price' => 15000,
            'is_available' => '1',
        ], $overrides);
    }

    public function test_admin_can_create_menu_with_photo_and_option_groups(): void
    {
        $pedas = OptionGroup::create(['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);
        $topping = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 3]);

        $this->post('/admin/menus', $this->payload([
            'image' => $this->fakeImage('mie.jpg'),
            'option_groups' => [$pedas->id, $topping->id],
        ]))->assertRedirect('/admin/menus');

        $menu = Menu::firstOrFail();
        $this->assertSame(15000, $menu->price);
        $this->assertTrue($menu->is_available);
        Storage::disk('public')->assertExists($menu->image);
        $this->assertSame([$pedas->id, $topping->id], $menu->optionGroups->pluck('id')->all());
    }

    public function test_menu_can_be_marked_unavailable(): void
    {
        $this->post('/admin/menus', $this->payload(['is_available' => '0']));

        $this->assertFalse(Menu::firstOrFail()->is_available);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->post('/admin/menus', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/admin/menus', $this->payload(['price' => 'gratis']))->assertSessionHasErrors('price');
        $this->post('/admin/menus', $this->payload(['price' => -1]))->assertSessionHasErrors('price');
        $this->post('/admin/menus', $this->payload(['category_id' => 999]))->assertSessionHasErrors('category_id');
        $this->post('/admin/menus', $this->payload([
            'image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors('image');
        $this->post('/admin/menus', $this->payload([
            'image' => $this->fakeImage('besar.png', 3000),
        ]))->assertSessionHasErrors('image');

        $this->assertDatabaseCount('menus', 0);
    }

    public function test_deleted_category_or_group_cannot_be_chosen(): void
    {
        $this->category->delete();
        $this->post('/admin/menus', $this->payload())->assertSessionHasErrors('category_id');

        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $group->delete();
        $live = Category::create(['name' => 'Minuman']);
        $this->post('/admin/menus', $this->payload(['category_id' => $live->id, 'option_groups' => [$group->id]]))
            ->assertSessionHasErrors('option_groups.0');
    }

    public function test_update_replaces_the_photo_and_removes_the_old_file(): void
    {
        $this->post('/admin/menus', $this->payload(['image' => $this->fakeImage('lama.jpg')]));
        $menu = Menu::firstOrFail();
        $oldPath = $menu->image;

        $this->put("/admin/menus/{$menu->id}", $this->payload([
            'name' => 'Mie Super Pedas',
            'image' => $this->fakeImage('baru.jpg'),
        ]))->assertRedirect('/admin/menus');

        $menu->refresh();
        $this->assertSame('Mie Super Pedas', $menu->name);
        $this->assertNotSame($oldPath, $menu->image);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($menu->image);
    }

    public function test_update_without_new_photo_keeps_the_old_one(): void
    {
        $this->post('/admin/menus', $this->payload(['image' => $this->fakeImage('a.jpg')]));
        $menu = Menu::firstOrFail();
        $path = $menu->image;

        $this->put("/admin/menus/{$menu->id}", $this->payload(['price' => 17000]));

        $this->assertSame($path, $menu->fresh()->image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_photo_can_be_removed(): void
    {
        $this->post('/admin/menus', $this->payload(['image' => $this->fakeImage('a.jpg')]));
        $menu = Menu::firstOrFail();
        $path = $menu->image;

        $this->put("/admin/menus/{$menu->id}", $this->payload(['remove_image' => '1']));

        $this->assertNull($menu->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_update_can_detach_option_groups(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $this->post('/admin/menus', $this->payload(['option_groups' => [$group->id]]));
        $menu = Menu::firstOrFail();
        $this->assertCount(1, $menu->optionGroups);

        $this->put("/admin/menus/{$menu->id}", $this->payload());

        $this->assertCount(0, $menu->fresh()->optionGroups);
    }

    public function test_editing_a_menu_keeps_its_link_to_a_group_that_is_in_the_trash(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $this->post('/admin/menus', $this->payload(['option_groups' => [$group->id]]));
        $menu = Menu::firstOrFail();
        $group->delete();

        $this->put("/admin/menus/{$menu->id}", $this->payload(['price' => 18000]));

        $this->assertDatabaseHas('menu_option_group', ['menu_id' => $menu->id, 'option_group_id' => $group->id]);
        $this->assertCount(0, $menu->fresh()->optionGroups, 'grup di Sampah tidak tampil di menu');
    }

    public function test_deleting_a_menu_is_soft_and_keeps_the_photo_file(): void
    {
        $this->post('/admin/menus', $this->payload(['image' => $this->fakeImage('a.jpg')]));
        $menu = Menu::firstOrFail();

        $this->delete("/admin/menus/{$menu->id}")->assertRedirect('/admin/menus');

        $this->assertSoftDeleted($menu);
        Storage::disk('public')->assertExists($menu->image);
        $this->get('/admin/menus')->assertDontSee('Mie Pedas');
    }

    // ---- Grup opsi dan opsi -------------------------------------------------

    public function test_creating_an_option_group_goes_to_its_manage_page(): void
    {
        $response = $this->post('/admin/option-groups', ['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);

        $group = OptionGroup::firstOrFail();
        $response->assertRedirect("/admin/option-groups/{$group->id}/edit");
        $this->assertTrue($group->isRequired());
    }

    public function test_option_group_rules_are_validated(): void
    {
        $this->post('/admin/option-groups', ['name' => 'X', 'min_select' => 3, 'max_select' => 2])
            ->assertSessionHasErrors('max_select');
        $this->post('/admin/option-groups', ['name' => 'X', 'max_select' => 0])
            ->assertSessionHasErrors('max_select');
        $this->post('/admin/option-groups', ['name' => '', 'max_select' => 1])
            ->assertSessionHasErrors('name');
    }

    public function test_options_can_be_added_updated_and_soft_deleted(): void
    {
        $group = OptionGroup::create(['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);

        $this->post("/admin/option-groups/{$group->id}/options", [
            'name' => 'Lv 3', 'extra_price' => 1000, 'sort' => 3, 'is_available' => '1',
        ])->assertRedirect("/admin/option-groups/{$group->id}/edit");

        $option = Option::firstOrFail();
        $this->assertSame($group->id, $option->option_group_id);
        $this->assertSame(1000, $option->extra_price);

        $this->put("/admin/options/{$option->id}", [
            'name' => 'Lv 3 Mantap', 'extra_price' => 1500, 'sort' => 3, 'is_available' => '0',
        ])->assertRedirect("/admin/option-groups/{$group->id}/edit");

        $option->refresh();
        $this->assertSame('Lv 3 Mantap', $option->name);
        $this->assertFalse($option->is_available);

        $this->delete("/admin/options/{$option->id}")->assertRedirect("/admin/option-groups/{$group->id}/edit");
        $this->assertSoftDeleted($option);
    }

    public function test_option_extra_price_cannot_be_negative(): void
    {
        $group = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);

        $this->post("/admin/option-groups/{$group->id}/options", ['name' => 'Keju', 'extra_price' => -500])
            ->assertSessionHasErrors('extra_price');
    }

    public function test_manage_page_shows_the_options(): void
    {
        $group = OptionGroup::create(['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);
        $group->options()->create(['name' => 'Lv 0', 'extra_price' => 0, 'sort' => 0, 'is_available' => true]);

        $this->get("/admin/option-groups/{$group->id}/edit")->assertOk()->assertSee('Lv 0');
    }

    public function test_one_group_can_be_shared_by_many_menus(): void
    {
        $group = OptionGroup::create(['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);

        $this->post('/admin/menus', $this->payload(['name' => 'Mie', 'option_groups' => [$group->id]]));
        $this->post('/admin/menus', $this->payload(['name' => 'Nasi', 'option_groups' => [$group->id]]));

        $this->assertSame(2, $group->attachedMenuCount());
    }
}
