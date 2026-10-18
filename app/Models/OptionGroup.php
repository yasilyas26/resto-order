<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class OptionGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'min_select', 'max_select'];

    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('sort')->orderBy('id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_option_group')->withPivot('sort');
    }

    public function isRequired(): bool
    {
        return $this->min_select >= 1;
    }

    /** Jumlah menu yang memasang grup ini (termasuk menu di Sampah). */
    public function attachedMenuCount(): int
    {
        return DB::table('menu_option_group')->where('option_group_id', $this->id)->count();
    }

    /** Jumlah kategori yang memasang grup ini sebagai opsi bawaan. */
    public function attachedCategoryCount(): int
    {
        return DB::table('category_option_group')->where('option_group_id', $this->id)->count();
    }

    public function trashLabel(): string
    {
        return $this->name;
    }

    public function restoreBlocker(): ?string
    {
        return null;
    }

    public function forceDeleteBlocker(): ?string
    {
        $menus = $this->attachedMenuCount();
        $categories = $this->attachedCategoryCount();

        return match (true) {
            $menus > 0 && $categories > 0 => "Masih terpasang di {$menus} menu dan {$categories} kategori.",
            $menus > 0 => "Masih terpasang di {$menus} menu.",
            $categories > 0 => "Masih terpasang di {$categories} kategori.",
            default => null,
        };
    }
}
