<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Daftar jenis data yang punya Sampah, plus penghapusan permanen massal
 * (tombol "Kosongkan Semua Sampah" dan perintah harian trash:purge).
 */
class Trash
{
    /** Data di Sampah disimpan selama ini sebelum otomatis dihapus permanen. */
    public const RETENTION_DAYS = 30;

    /**
     * slug di URL => [judul, model, tab filter].
     * Urutannya penting untuk penghapusan massal: anak dulu (opsi, menu), baru induknya (grup, kategori),
     * supaya induk yang tadinya terhalang bisa ikut terhapus di putaran yang sama.
     */
    public const TYPES = [
        'options' => ['Opsi', Option::class, 'opsi'],
        'menus' => ['Menu', Menu::class, 'menu'],
        'option-groups' => ['Grup opsi', OptionGroup::class, 'opsi'],
        'categories' => ['Kategori', Category::class, 'kategori'],
        'tables' => ['Meja', Table::class, 'meja'],
    ];

    /** Tab filter di halaman Sampah: kunci => judul. */
    public const TABS = [
        'semua' => 'Semua Data Terhapus',
        'menu' => 'Menu & Hidangan',
        'kategori' => 'Kategori',
        'opsi' => 'Grup Opsi',
        'meja' => 'Meja Resto',
    ];

    /** @return Collection<int, array{slug: string, label: string, item: Model}> semua isi Sampah, yang paling baru dihapus di atas. */
    public static function items(?string $tab = null): Collection
    {
        $rows = collect();

        foreach (self::TYPES as $slug => [$label, $model, $tabKey]) {
            if ($tab && $tab !== 'semua' && $tab !== $tabKey) {
                continue;
            }

            $query = $model::onlyTrashed();

            // Eager load relasi yang dipakai aturan pulihkan/hapus permanen, supaya tidak N+1.
            match ($model) {
                Menu::class => $query->with('category'),
                Option::class => $query->with('group'),
                default => null,
            };

            foreach ($query->get() as $item) {
                $rows->push(['slug' => $slug, 'label' => $label, 'item' => $item]);
            }
        }

        return $rows->sortByDesc(fn ($row) => $row['item']->deleted_at)->values();
    }

    /** @return array<string, int> jumlah item per tab. */
    public static function counts(): array
    {
        $counts = array_fill_keys(array_keys(self::TABS), 0);

        foreach (self::TYPES as [, $model, $tabKey]) {
            $n = $model::onlyTrashed()->count();
            $counts[$tabKey] += $n;
            $counts['semua'] += $n;
        }

        return $counts;
    }

    /** Sisa hari sebelum dihapus otomatis (0 = sudah waktunya). */
    public static function daysLeft(Model $item): int
    {
        return max(0, (int) ceil(now()->diffInDays($item->deleted_at->copy()->addDays(self::RETENTION_DAYS), false)));
    }

    /**
     * Hapus permanen semua yang boleh. Yang masih dirujuk data lain (mis. menu yang pernah dipesan) dilewati.
     *
     * @param  int|null  $olderThanDays  hanya yang sudah di Sampah selama ini; null = semuanya.
     * @return array{deleted: int, skipped: int}
     */
    public static function purge(?int $olderThanDays = null): array
    {
        $deleted = 0;
        $skipped = 0;

        foreach (self::TYPES as [, $model]) {
            $query = $model::onlyTrashed();

            if ($olderThanDays !== null) {
                $query->where('deleted_at', '<=', now()->subDays($olderThanDays));
            }

            foreach ($query->get() as $item) {
                if ($item->forceDeleteBlocker()) {
                    $skipped++;

                    continue;
                }

                $item->forceDelete();
                $deleted++;
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }
}
