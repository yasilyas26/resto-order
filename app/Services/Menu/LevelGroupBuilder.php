<?php

namespace App\Services\Menu;

use App\Models\Menu;
use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;

/**
 * Membuat satu grup opsi berisi deretan level (mis. "Level Pedas": Level 0 sampai Level 8) sekaligus,
 * lalu memasangnya ke menu. Gunanya supaya admin tidak perlu membuat opsi satu per satu.
 *
 * Grup tetap grup biasa yang bisa dipakai ulang oleh menu lain (keputusan PRD): kalau grup dengan nama
 * yang sama sudah ada, dipakai lagi dan hanya level yang belum ada yang ditambahkan.
 */
class LevelGroupBuilder
{
    /** Batas jumlah level sekali buat, supaya salah ketik (0 sampai 99999) tidak membuat ribuan opsi. */
    public const MAX_LEVELS = 21;

    /**
     * @param  array{group_name: string, prefix: string, from: int, to: int, required: bool, surcharge_from: ?int, surcharge: int}  $spec
     */
    public function apply(Menu $menu, array $spec): OptionGroup
    {
        return DB::transaction(function () use ($menu, $spec) {
            $group = OptionGroup::where('name', $spec['group_name'])->orderBy('id')->first()
                ?? OptionGroup::create([
                    'name' => $spec['group_name'],
                    'min_select' => $spec['required'] ? 1 : 0,
                    'max_select' => 1,
                ]);

            // Pengaturan grup yang sudah ada (wajib/pilih berapa) sengaja tidak diubah: grup itu mungkin dipakai menu lain.
            $existing = $group->options()->pluck('name')->all();

            for ($level = $spec['from']; $level <= $spec['to']; $level++) {
                $name = "{$spec['prefix']} {$level}";

                // Level yang sudah ada dibiarkan apa adanya (harga yang sudah diatur admin tidak ditimpa).
                if (in_array($name, $existing, true)) {
                    continue;
                }

                $group->options()->create([
                    'name' => $name,
                    'extra_price' => $this->surchargeFor($level, $spec),
                    'sort' => $level,
                    'is_available' => true,
                ]);
            }

            if (! $menu->optionGroups()->where('option_groups.id', $group->id)->exists()) {
                $next = ((int) $menu->optionGroups()->max('menu_option_group.sort')) + 1;
                $menu->optionGroups()->attach($group->id, ['sort' => $next]);
            }

            return $group;
        });
    }

    /** Tambahan harga mulai level tertentu (mis. Level 3 ke atas +Rp1.000). */
    private function surchargeFor(int $level, array $spec): int
    {
        if ($spec['surcharge_from'] === null || $level < $spec['surcharge_from']) {
            return 0;
        }

        return $spec['surcharge'];
    }
}
