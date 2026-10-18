<?php

namespace App\Services\Ordering;

use App\Models\Menu;
use App\Models\Option;
use Illuminate\Support\Collection;

/**
 * Memeriksa dan menghitung ulang isi keranjang di server.
 *
 * Browser hanya mengirim ID menu, ID opsi, jumlah, dan catatan. Nama dan harga TIDAK dibaca dari
 * browser sama sekali: semuanya diambil dari database, sehingga harga tidak bisa dimanipulasi.
 */
class CartPricer
{
    /**
     * @param  list<array{menu_id: int, qty: int, note?: ?string, options?: list<int>}>  $items
     * @return array{lines: list<array<string, mixed>>, subtotal: int}
     *
     * @throws InvalidCartException
     */
    public function price(array $items): array
    {
        $menus = Menu::with(['optionGroups.options', 'category.optionGroups.options'])
            ->whereIn('id', collect($items)->pluck('menu_id')->unique())
            ->get()
            ->keyBy('id');

        $errors = [];
        $lines = [];

        foreach ($items as $item) {
            $menu = $menus->get($item['menu_id']);

            if (! $menu) {
                $errors[] = 'Ada menu di keranjang yang sudah tidak tersedia. Hapus lalu pesan ulang.';

                continue;
            }

            if (! $menu->is_available) {
                $errors[] = "{$menu->name} sedang habis. Hapus dari keranjang untuk melanjutkan.";

                continue;
            }

            $selected = $this->resolveOptions($menu, array_unique($item['options'] ?? []), $errors);

            if ($selected === null) {
                continue;
            }

            $extra = $selected->sum(fn (Option $option) => $option->extra_price);
            $qty = (int) $item['qty'];

            $lines[] = [
                'menu' => $menu,
                'qty' => $qty,
                'note' => filled($item['note'] ?? null) ? trim($item['note']) : null,
                'options' => $selected,
                'unit_price' => $menu->price,
                'line_total' => ($menu->price + $extra) * $qty,
            ];
        }

        if ($errors !== []) {
            throw new InvalidCartException(array_values(array_unique($errors)));
        }

        return [
            'lines' => $lines,
            'subtotal' => array_sum(array_column($lines, 'line_total')),
        ];
    }

    /**
     * Mengubah daftar ID opsi jadi model Option, sambil memastikan tiap opsi memang milik grup yang
     * terpasang di menu ini, tersedia, dan jumlah pilihan per grup ada di antara min dan max.
     *
     * @param  list<int>  $optionIds
     * @param  list<string>  $errors
     * @return Collection<int, Option>|null null kalau ada yang tidak valid (pesan dimasukkan ke $errors)
     */
    private function resolveOptions(Menu $menu, array $optionIds, array &$errors): ?Collection
    {
        // Hanya opsi dari grup aktif yang terpasang di menu ini yang boleh dipilih.
        $groups = $menu->effectiveOptionGroups();
        $allowed = $groups->flatMap(
            fn ($group) => $group->options->each(fn (Option $option) => $option->setRelation('group', $group))
        )->keyBy('id');

        $selected = collect();
        $valid = true;

        foreach ($optionIds as $id) {
            $option = $allowed->get($id);

            if (! $option) {
                $errors[] = "Ada pilihan yang tidak valid untuk {$menu->name}.";
                $valid = false;

                continue;
            }

            if (! $option->is_available) {
                $errors[] = "Pilihan \"{$option->name}\" untuk {$menu->name} sedang habis.";
                $valid = false;

                continue;
            }

            $selected->push($option);
        }

        foreach ($groups as $group) {
            $count = $selected->filter(fn (Option $option) => $option->option_group_id === $group->id)->count();

            if ($count < $group->min_select) {
                $errors[] = "Pilih minimal {$group->min_select} \"{$group->name}\" untuk {$menu->name}.";
                $valid = false;
            }

            if ($count > $group->max_select) {
                $errors[] = "Pilihan \"{$group->name}\" untuk {$menu->name} maksimal {$group->max_select}.";
                $valid = false;
            }
        }

        return $valid ? $selected : null;
    }
}
