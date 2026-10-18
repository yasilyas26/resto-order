<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Menu;
use App\Models\OptionGroup;
use App\Models\User;
use App\Services\Ordering\CartPricer;
use App\Services\Ordering\InvalidCartException;
use App\Support\MenuCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Grup opsi bawaan kategori: menu di kategori itu otomatis mendapat grupnya, kategori lain tidak. */
class CategoryOptionGroupsTest extends TestCase
{
    use RefreshDatabase;

    private Category $mie;

    private Category $minuman;

    private OptionGroup $level;

    private Menu $miePedas;

    private Menu $esTeh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->level = OptionGroup::create(['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);
        $this->level->options()->create(['name' => 'Level 0', 'extra_price' => 0, 'sort' => 0, 'is_available' => true]);
        $this->level->options()->create(['name' => 'Level 3', 'extra_price' => 1000, 'sort' => 1, 'is_available' => true]);

        $this->mie = Category::create(['name' => 'Mie']);
        $this->minuman = Category::create(['name' => 'Minuman']);
        $this->mie->optionGroups()->attach($this->level->id, ['sort' => 0]);

        $this->miePedas = Menu::create(['category_id' => $this->mie->id, 'name' => 'Mie Pedas', 'price' => 15000, 'is_available' => true]);
        $this->esTeh = Menu::create(['category_id' => $this->minuman->id, 'name' => 'Es Teh', 'price' => 5000, 'is_available' => true]);
    }

    public function test_menu_gets_the_groups_of_its_category_and_other_categories_do_not(): void
    {
        $this->assertSame([$this->level->id], $this->miePedas->effectiveOptionGroups()->pluck('id')->all());
        $this->assertSame([], $this->esTeh->effectiveOptionGroups()->pluck('id')->all());
    }

    public function test_menu_can_opt_out_of_the_category_groups(): void
    {
        $this->miePedas->update(['skip_category_options' => true]);

        $this->assertSame([], $this->miePedas->fresh()->effectiveOptionGroups()->all());
    }

    public function test_a_group_attached_to_both_category_and_menu_appears_once(): void
    {
        $this->miePedas->optionGroups()->attach($this->level->id, ['sort' => 0]);

        $this->assertCount(1, $this->miePedas->fresh()->effectiveOptionGroups());
    }

    public function test_visitor_menu_data_has_the_category_level_only_for_that_category(): void
    {
        $groups = MenuCatalog::forScript(MenuCatalog::categories());

        $this->assertSame(['Level pedas'], array_column($groups[$this->miePedas->id]['groups'], 'name'));
        $this->assertSame([], $groups[$this->esTeh->id]['groups']);
    }

    public function test_server_prices_category_options_and_requires_the_mandatory_group(): void
    {
        $lv3 = $this->level->options()->where('name', 'Level 3')->firstOrFail();
        $pricer = app(CartPricer::class);

        $result = $pricer->price([['menu_id' => $this->miePedas->id, 'qty' => 2, 'options' => [$lv3->id]]]);
        $this->assertSame(32000, $result['subtotal']); // (15.000 + 1.000) x 2

        $this->expectException(InvalidCartException::class);
        $pricer->price([['menu_id' => $this->miePedas->id, 'qty' => 1, 'options' => []]]); // level wajib
    }

    public function test_category_options_are_rejected_for_a_menu_in_another_category(): void
    {
        $lv3 = $this->level->options()->where('name', 'Level 3')->firstOrFail();

        $this->expectException(InvalidCartException::class);
        app(CartPricer::class)->price([['menu_id' => $this->esTeh->id, 'qty' => 1, 'options' => [$lv3->id]]]);
    }

    public function test_category_options_are_rejected_when_the_menu_opted_out(): void
    {
        $this->miePedas->update(['skip_category_options' => true]);
        $lv3 = $this->level->options()->where('name', 'Level 3')->firstOrFail();

        $this->expectException(InvalidCartException::class);
        app(CartPricer::class)->price([['menu_id' => $this->miePedas->id, 'qty' => 1, 'options' => [$lv3->id]]]);
    }

    public function test_admin_attaches_and_detaches_groups_from_the_category_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put("/admin/categories/{$this->minuman->id}", ['name' => 'Minuman', 'option_groups' => [$this->level->id]])
            ->assertRedirect('/admin/categories');
        $this->assertSame([$this->level->id], $this->minuman->optionGroups()->pluck('option_groups.id')->all());

        $this->put("/admin/categories/{$this->minuman->id}", ['name' => 'Minuman'])->assertRedirect();
        $this->assertSame([], $this->minuman->optionGroups()->pluck('option_groups.id')->all());
    }

    public function test_admin_forms_render_and_menu_form_saves_the_opt_out(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/categories')->assertOk()->assertSee('Level pedas');
        $this->get("/admin/categories/{$this->mie->id}/edit")->assertOk()->assertSee('Grup opsi bawaan kategori');
        $this->get("/admin/menus/{$this->miePedas->id}/edit")->assertOk()->assertSee('Jangan pakai grup bawaan kategori');

        $this->put("/admin/menus/{$this->miePedas->id}", [
            'category_id' => $this->mie->id,
            'name' => 'Mie Pedas',
            'price' => 15000,
            'is_available' => '1',
            'skip_category_options' => '1',
        ])->assertRedirect('/admin/menus');

        $this->assertTrue($this->miePedas->fresh()->skip_category_options);
    }

    public function test_group_attached_to_a_category_cannot_be_force_deleted(): void
    {
        $this->assertSame('Masih terpasang di 1 kategori.', $this->level->forceDeleteBlocker());
    }
}
