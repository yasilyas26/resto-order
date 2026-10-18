<?php

namespace App\Support;

class PaymentLabel
{
    /** Nama metode bayar yang enak dibaca, dari payment_type Midtrans. */
    public static function for(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        return match ($type) {
            'qris' => 'QRIS',
            'gopay' => 'GoPay',
            'shopeepay' => 'ShopeePay',
            'bank_transfer' => 'Transfer Virtual Account',
            'echannel' => 'Mandiri Bill',
            'credit_card' => 'Kartu Kredit/Debit',
            'cstore' => 'Gerai/Minimarket',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
