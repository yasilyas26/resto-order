<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris = satu percobaan bayar ke Midtrans. Tidak pernah dihapus. */
class Payment extends Model
{
    protected $fillable = [
        'midtrans_order_id',
        'snap_token',
        'transaction_status',
        'payment_type',
        'gross_amount',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Percobaan yang belum final: belum ada kabar dari Midtrans, atau masih menunggu pelanggan membayar. */
    public function isOpen(): bool
    {
        return in_array($this->transaction_status, [null, 'pending'], true);
    }
}
