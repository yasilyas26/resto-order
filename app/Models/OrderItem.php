<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'menu_id',
        'menu_name',
        'category_name',
        'unit_price',
        'qty',
        'note',
        'line_total',
    ];

    // is_unavailable dan served_at sengaja tidak mass-assignable: hanya diubah karyawan lewat OrderWorkflow.
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'qty' => 'integer',
            'line_total' => 'integer',
            'is_unavailable' => 'boolean',
            'served_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
