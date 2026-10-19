<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Support\Collection;

/** Data menu untuk halaman pengunjung. Menu, kategori, grup, dan opsi yang dihapus tidak ikut. */
class MenuCatalog
{
    /** @return Collection<int, Category> kategori yang punya minimal satu menu */
    public static function categories(): Collection
    {
        return Category::with(['menus' => fn ($query) => $query->with(['optionGroups.options', 'category.optionGroups.options'])->orderBy('name')])
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->menus->isNotEmpty())
            ->values();
    }

    /**
     * Bentuk data yang dibaca Alpine (modal opsi dan keranjang), dikunci per ID menu.
     * Harga di sini hanya untuk tampilan; server menghitung ulang saat checkout.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<int, array<string, mixed>>
     */
    public static function forScript(Collection $categories): array
    {
        $result = [];

        foreach ($categories as $category) {
            foreach ($category->menus as $menu) {
                $result[$menu->id] = self::menuToArray($menu, $category->name);
            }
        }

        return $result;
    }

    private static function menuToArray(Menu $menu, string $categoryName): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'description' => $menu->description,
            'price' => $menu->price,
            'image' => $menu->imageUrl(),
            'category' => $categoryName,
            'available' => $menu->is_available,
            'stock' => $menu->stock, // null = tidak dibatasi
            'groups' => $menu->effectiveOptionGroups()
                ->filter(fn (OptionGroup $group) => $group->options->isNotEmpty())
                ->map(fn (OptionGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'min' => $group->min_select,
                    'max' => $group->max_select,
                    'options' => $group->options->map(fn (Option $option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price' => $option->extra_price,
                        'available' => $option->is_available,
                    ])->values()->all(),
                ])->values()->all(),
        ];
    }
}
