<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'sort'];

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    /** Grup opsi bawaan: otomatis dipakai semua menu di kategori ini. */
    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'category_option_group')
            ->withPivot('sort')
            ->orderByPivot('sort');
    }

    public function trashLabel(): string
    {
        return $this->name;
    }

    /** Alasan kategori ini belum boleh dipulihkan, atau null kalau boleh. */
    public function restoreBlocker(): ?string
    {
        // Query biasa sudah mengabaikan baris terhapus, jadi ini cek terhadap kategori aktif saja.
        return static::where('name', $this->name)->exists()
            ? "Sudah ada kategori aktif bernama \"{$this->name}\"."
            : null;
    }

    /** Alasan kategori ini belum boleh dihapus permanen, atau null kalau boleh. */
    public function forceDeleteBlocker(): ?string
    {
        return $this->menus()->withTrashed()->exists()
            ? 'Masih ada menu (termasuk yang di Sampah) di kategori ini.'
            : null;
    }
}
