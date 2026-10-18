<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Table;

trait BuildsMenu
{
    /**
     * Data contoh: meja A07, "Mie Pedas" (Rp15.000) dengan grup wajib "Level pedas" (pilih 1) dan
     * grup opsional "Topping" (maksimal 2, Bakso sedang habis), plus "Es Teh" (Rp5.000) tanpa opsi.
     *
     * @return array{table: Table, category: Category, mie: Menu, esTeh: Menu, pedas: OptionGroup, topping: OptionGroup, lv0: Option, lv3: Option, telur: Option, keju: Option, bakso: Option}
     */
    protected function menuFixture(): array
    {
        $table = Table::create(['number' => 'A07']);
        $category = Category::create(['name' => 'Mie']);

        $pedas = OptionGroup::create(['name' => 'Level pedas', 'min_select' => 1, 'max_select' => 1]);
        $lv0 = $pedas->options()->create(['name' => 'Level 0', 'extra_price' => 0, 'sort' => 0, 'is_available' => true]);
        $lv3 = $pedas->options()->create(['name' => 'Level 3', 'extra_price' => 1000, 'sort' => 3, 'is_available' => true]);

        $topping = OptionGroup::create(['name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $telur = $topping->options()->create(['name' => 'Telur', 'extra_price' => 3000, 'sort' => 0, 'is_available' => true]);
        $keju = $topping->options()->create(['name' => 'Keju', 'extra_price' => 4000, 'sort' => 1, 'is_available' => true]);
        $bakso = $topping->options()->create(['name' => 'Bakso', 'extra_price' => 5000, 'sort' => 2, 'is_available' => false]);

        $mie = Menu::create(['category_id' => $category->id, 'name' => 'Mie Pedas', 'price' => 15000, 'is_available' => true]);
        $mie->optionGroups()->attach([$pedas->id => ['sort' => 0], $topping->id => ['sort' => 1]]);

        $esTeh = Menu::create(['category_id' => $category->id, 'name' => 'Es Teh', 'price' => 5000, 'is_available' => true]);

        return compact('table', 'category', 'mie', 'esTeh', 'pedas', 'topping', 'lv0', 'lv3', 'telur', 'keju', 'bakso');
    }
}
