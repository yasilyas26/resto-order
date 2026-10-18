<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Menu extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'image',
        'is_available',
        'skip_category_options',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_available' => 'boolean',
            'skip_category_options' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Foto hanya dihapus dari storage saat menu dihapus permanen (PRD),
        // supaya menu di Sampah masih bisa dipulihkan lengkap dengan fotonya.
        static::forceDeleted(function (Menu $menu) {
            if ($menu->image) {
                Storage::disk('public')->delete($menu->image);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'menu_option_group')
            ->withPivot('sort')
            ->orderByPivot('sort');
    }

    /**
     * Satu-satunya sumber kebenaran grup opsi sebuah menu: grup bawaan kategori
     * (kecuali menu ini mematikannya) digabung dengan grup yang dipasang langsung,
     * tanpa duplikat. Halaman menu dan hitung harga wajib lewat sini.
     */
    public function effectiveOptionGroups(): Collection
    {
        $fromCategory = $this->skip_category_options
            ? collect()
            : $this->category->optionGroups;

        return $fromCategory->concat($this->optionGroups)->unique('id')->values();
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /** Tabel order_items baru dibuat di Fase 3, jadi dicek dulu keberadaannya. */
    public function hasBeenOrdered(): bool
    {
        return Schema::hasTable('order_items')
            && DB::table('order_items')->where('menu_id', $this->id)->exists();
    }

    public function trashLabel(): string
    {
        return $this->name;
    }

    public function restoreBlocker(): ?string
    {
        return $this->category->trashed()
            ? "Pulihkan kategori \"{$this->category->name}\" dulu."
            : null;
    }

    public function forceDeleteBlocker(): ?string
    {
        return $this->hasBeenOrdered()
            ? 'Menu ini sudah pernah dipesan, datanya dipertahankan untuk riwayat order.'
            : null;
    }
}
