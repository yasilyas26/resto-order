<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Menu;
use App\Models\OptionGroup;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Level cepat" di form menu: Level 0 sampai 8 dibuat sekaligus, tanpa membuat opsi satu per satu. */
class MenuLevelsTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $this->category = Category::create(['name' => 'Mie Pedas']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name' => 'Mie Gacor',
            'price' => 15000,
            'is_available' => '1',
            'level_enabled' => '1',
            'level_group_name' => 'Level Pedas',
            'level_prefix' => 'Level',
            'level_from' => 0,
            'level_to' => 8,
            'level_required' => '1',
        ], $overrides);
    }

    public function test_one_form_creates_the_menu_and_all_levels_zero_to_eight(): void
    {
        $this->post('/admin/menus', $this->payload())
            ->assertRedirect('/admin/menus')
            ->assertSessionHas('status', 'Menu ditambahkan. Level 0–8 dipasang lewat grup "Level Pedas".');

        $menu = Menu::firstOrFail();
        $group = $menu->optionGroups()->firstOrFail();

        $this->assertSame('Level Pedas', $group->name);
        $this->assertSame(
            ['Level 0', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Level 6', 'Level 7', 'Level 8'],
            $group->options->pluck('name')->all(),
        );
        $this->assertTrue($group->options->every(fn ($o) => $o->extra_price === 0 && $o->is_available));
    }

    public function test_the_group_is_required_choose_one_by_default_and_can_be_optional(): void
    {
        $this->post('/admin/menus', $this->payload());
        $required = OptionGroup::where('name', 'Level Pedas')->firstOrFail();
        $this->assertSame([1, 1], [$required->min_select, $required->max_select]);

        $this->post('/admin/menus', $this->payload(['name' => 'Es Teh', 'level_group_name' => 'Level Manis', 'level_required' => '0']));
        $optional = OptionGroup::where('name', 'Level Manis')->firstOrFail();
        $this->assertSame([0, 1], [$optional->min_select, $optional->max_select]);
    }

    public function test_surcharge_applies_from_the_chosen_level_upwards(): void
    {
        $this->post('/admin/menus', $this->payload(['level_surcharge_from' => 3, 'level_surcharge' => 1000]));

        $prices = OptionGroup::firstOrFail()->options->mapWithKeys(fn ($o) => [$o->name => $o->extra_price])->all();

        $this->assertSame(0, $prices['Level 2']);
        $this->assertSame(1000, $prices['Level 3']);
        $this->assertSame(1000, $prices['Level 8']);
    }

    public function test_a_custom_range_and_prefix_work(): void
    {
        $this->post('/admin/menus', $this->payload(['level_group_name' => 'Porsi', 'level_prefix' => 'Ukuran', 'level_from' => 1, 'level_to' => 3]));

        $this->assertSame(['Ukuran 1', 'Ukuran 2', 'Ukuran 3'], OptionGroup::where('name', 'Porsi')->firstOrFail()->options->pluck('name')->all());
    }

    public function test_the_levels_show_up_for_customers_in_order(): void
    {
        $this->post('/admin/menus', $this->payload(['level_surcharge_from' => 3, 'level_surcharge' => 1000]));
        $table = Table::create(['number' => 'A07']);

        $html = $this->withSession(['table_id' => $table->id])->get('/menu')->assertOk()->getContent();

        $this->assertStringContainsString('Level Pedas', $html);
        $this->assertMatchesRegularExpression('/Level 0.*Level 1.*Level 2.*Level 3.*Level 8/s', $html);
    }

    public function test_a_second_menu_reuses_the_group_instead_of_duplicating_it(): void
    {
        $this->post('/admin/menus', $this->payload());
        $this->post('/admin/menus', $this->payload(['name' => 'Mie Hompimpa']));

        $this->assertSame(1, OptionGroup::where('name', 'Level Pedas')->count());
        $this->assertSame(9, OptionGroup::firstOrFail()->options()->count(), 'level tidak digandakan');
        $this->assertSame(2, OptionGroup::firstOrFail()->attachedMenuCount());
    }

    public function test_extending_an_existing_group_adds_only_the_missing_levels_and_keeps_existing_prices(): void
    {
        $this->post('/admin/menus', $this->payload(['level_to' => 4, 'level_surcharge_from' => 3, 'level_surcharge' => 1000]));
        $group = OptionGroup::firstOrFail();
        $group->options()->where('name', 'Level 3')->update(['extra_price' => 2500]); // admin mengubah harga manual

        $this->post('/admin/menus', $this->payload(['name' => 'Mie Hompimpa', 'level_to' => 6, 'level_surcharge_from' => 3, 'level_surcharge' => 1000]));

        $options = $group->fresh()->options->keyBy('name');
        $this->assertCount(7, $options);
        $this->assertSame(2500, $options['Level 3']->extra_price, 'harga yang sudah diatur tidak ditimpa');
        $this->assertSame(1000, $options['Level 5']->extra_price, 'level baru memakai harga dari form');
    }

    public function test_an_existing_groups_rules_are_not_changed_by_another_menu(): void
    {
        OptionGroup::create(['name' => 'Level Pedas', 'min_select' => 0, 'max_select' => 2]);

        $this->post('/admin/menus', $this->payload(['level_required' => '1']));

        $group = OptionGroup::firstOrFail();
        $this->assertSame([0, 2], [$group->min_select, $group->max_select], 'grup mungkin dipakai menu lain');
    }

    public function test_editing_a_menu_can_add_levels_and_keeps_its_other_groups(): void
    {
        $topping = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $menu = Menu::create(['category_id' => $this->category->id, 'name' => 'Mie Gacor', 'price' => 15000, 'is_available' => true]);
        $menu->optionGroups()->attach($topping->id, ['sort' => 0]);

        $this->put("/admin/menus/{$menu->id}", $this->payload(['option_groups' => [$topping->id]]))->assertRedirect('/admin/menus');

        $names = $menu->fresh()->optionGroups->pluck('name')->all();
        $this->assertSame(['Topping', 'Level Pedas'], $names, 'level baru dipasang setelah grup yang sudah ada');
    }

    public function test_applying_levels_twice_to_the_same_menu_does_not_attach_it_twice(): void
    {
        $this->post('/admin/menus', $this->payload());
        $menu = Menu::firstOrFail();

        $this->put("/admin/menus/{$menu->id}", $this->payload())->assertSessionDoesntHaveErrors();

        $this->assertSame(1, $menu->fresh()->optionGroups()->count());
        $this->assertSame(9, OptionGroup::firstOrFail()->options()->count());
    }

    // ---- tanpa level / validasi --------------------------------------------------

    public function test_nothing_is_created_when_the_level_box_is_not_ticked(): void
    {
        $this->post('/admin/menus', $this->payload(['level_enabled' => '0']))->assertSessionDoesntHaveErrors();

        $this->assertSame(0, OptionGroup::count());
        $this->assertSame(1, Menu::count());
    }

    public function test_invalid_level_fields_are_ignored_when_the_box_is_not_ticked(): void
    {
        $this->post('/admin/menus', $this->payload(['level_enabled' => '0', 'level_from' => 'abc', 'level_to' => '-5', 'level_group_name' => '']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Menu::count());
    }

    public function test_the_level_range_is_validated(): void
    {
        $this->post('/admin/menus', $this->payload(['level_from' => 5, 'level_to' => 2]))->assertSessionHasErrors('level_to');
        $this->post('/admin/menus', $this->payload(['level_from' => 'a']))->assertSessionHasErrors('level_from');
        $this->post('/admin/menus', $this->payload(['level_to' => '']))->assertSessionHasErrors('level_to');
        $this->post('/admin/menus', $this->payload(['level_to' => 101]))->assertSessionHasErrors('level_to');
        $this->post('/admin/menus', $this->payload(['level_group_name' => '']))->assertSessionHasErrors('level_group_name');
        $this->post('/admin/menus', $this->payload(['level_prefix' => '']))->assertSessionHasErrors('level_prefix');
        $this->post('/admin/menus', $this->payload(['level_surcharge' => -1000, 'level_surcharge_from' => 3]))->assertSessionHasErrors('level_surcharge');

        $this->assertSame(0, Menu::count(), 'menu tidak dibuat setengah-setengah kalau levelnya tidak sah');
        $this->assertSame(0, OptionGroup::count());
    }

    public function test_too_many_levels_at_once_are_rejected(): void
    {
        $this->post('/admin/menus', $this->payload(['level_from' => 0, 'level_to' => 21]))
            ->assertSessionHasErrors(['level_to' => 'Maksimal 21 level sekali buat.']);

        $this->post('/admin/menus', $this->payload(['level_from' => 0, 'level_to' => 20]))->assertSessionDoesntHaveErrors();
        $this->assertSame(21, OptionGroup::firstOrFail()->options()->count());
    }

    public function test_a_trashed_group_with_the_same_name_is_not_reused(): void
    {
        OptionGroup::create(['name' => 'Level Pedas', 'min_select' => 1, 'max_select' => 1])->delete();

        $this->post('/admin/menus', $this->payload());

        $this->assertSame(1, OptionGroup::count(), 'grup aktif baru dibuat');
        $this->assertSame(2, OptionGroup::withTrashed()->count());
        $this->assertSame(9, OptionGroup::firstOrFail()->options()->count());
    }

    public function test_the_form_offers_the_level_builder_with_sensible_defaults(): void
    {
        $this->get('/admin/menus/create')->assertOk()
            ->assertSee('Tambahkan level')
            ->assertSee('Level Pedas')
            ->assertSee('Pembeli wajib memilih satu level')
            ->assertSee('Yang akan dibuat');

        $menu = Menu::create(['category_id' => $this->category->id, 'name' => 'Mie Gacor', 'price' => 15000, 'is_available' => true]);
        $this->get("/admin/menus/{$menu->id}/edit")->assertOk()->assertSee('Tambahkan level');
    }

    public function test_the_form_keeps_the_level_settings_after_a_validation_error(): void
    {
        $this->from('/admin/menus/create')->post('/admin/menus', $this->payload(['name' => '', 'level_to' => 6, 'level_group_name' => 'Level Manis']));

        $html = $this->get('/admin/menus/create')->getContent();

        $this->assertStringContainsString('Level Manis', $html);
        $this->assertMatchesRegularExpression('/enabled\\\\u0022:true/', $html, 'kotak level tetap tercentang');
    }
}
