<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Option extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'extra_price', 'is_available', 'sort'];

    protected function casts(): array
    {
        return [
            'extra_price' => 'integer',
            'is_available' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id')->withTrashed();
    }

    public function trashLabel(): string
    {
        return "{$this->group->name} › {$this->name}";
    }

    public function restoreBlocker(): ?string
    {
        return $this->group->trashed()
            ? "Pulihkan grup \"{$this->group->name}\" dulu."
            : null;
    }

    public function forceDeleteBlocker(): ?string
    {
        return $this->group->attachedMenuCount() > 0 || $this->group->attachedCategoryCount() > 0
            ? 'Grup opsinya masih terpasang di menu atau kategori.'
            : null;
    }
}
