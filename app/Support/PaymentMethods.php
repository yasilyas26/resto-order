<?php

namespace App\Support;

/**
 * Metode bayar di halaman "Pilih Metode Pembayaran". Tiap kelompok (e-wallet dan Virtual Account)
 * punya pilihan di dalamnya (dropdown). Semuanya dibuat langsung lewat Core API Midtrans, jadi nomor VA atau QR-nya
 * langsung tampil di halaman kita (tanpa popup). Kartu kredit/debit sengaja tidak ditawarkan.
 */
class PaymentMethods
{
    /** @return array<string, array{label: string, hint: string, icon: string, prompt: string|null, options: array<string, string>}> */
    public static function groups(): array
    {
        return [
            'ewallet' => [
                'label' => 'E-Wallet & QRIS',
                'hint' => 'GoPay, ShopeePay, atau QRIS dari e-wallet dan m-banking apa pun',
                'icon' => 'wallet',
                'prompt' => 'Pilih e-wallet',
                'options' => ['qris' => 'QRIS (semua e-wallet & m-banking)', 'gopay' => 'GoPay', 'shopeepay' => 'ShopeePay'],
            ],
            'va' => [
                'label' => 'Transfer Virtual Account',
                'hint' => 'Transfer dari ATM atau mobile banking',
                'icon' => 'bank',
                'prompt' => 'Pilih bank',
                'options' => ['bca_va' => 'BCA', 'bni_va' => 'BNI', 'bri_va' => 'BRI', 'permata_va' => 'Permata', 'cimb_va' => 'CIMB Niaga', 'mandiri_bill' => 'Mandiri (Bill Payment)'],
            ],
        ];
    }

    /** @return list<string> semua pilihan yang sah. */
    public static function keys(): array
    {
        return array_keys(array_merge(...array_column(self::groups(), 'options')));
    }

    /** Kelompok dari sebuah pilihan, atau null kalau tidak dikenal. */
    public static function groupOf(?string $key): ?string
    {
        foreach (self::groups() as $group => $data) {
            if (array_key_exists((string) $key, $data['options'])) {
                return $group;
            }
        }

        return null;
    }

    public static function label(?string $key): ?string
    {
        $group = self::groupOf($key);

        return $group ? self::groups()[$group]['options'][$key] : null;
    }

    /** Nilai enabled_payments Snap. Semua metode sekarang dibuat lewat Core API, jadi Snap hanya tersisa untuk percobaan lama tanpa pilihan (null = semua metode). */
    public static function enabledFor(?string $key): ?array
    {
        return null;
    }

    /**
     * Parameter Core API (payment_type + blok khususnya) untuk satu pilihan. Null kalau pilihannya tidak dikenal / tanpa pilihan.
     *
     * @return array<string, mixed>|null
     */
    public static function chargeSpec(?string $key): ?array
    {
        $callback = route('order.finish');

        return match ($key) {
            'qris' => ['payment_type' => 'qris', 'qris' => ['acquirer' => 'gopay']],
            'gopay' => ['payment_type' => 'gopay', 'gopay' => ['enable_callback' => true, 'callback_url' => $callback]],
            'shopeepay' => ['payment_type' => 'shopeepay', 'shopeepay' => ['callback_url' => $callback]],
            'bca_va' => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'bca']],
            'bni_va' => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'bni']],
            'bri_va' => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'bri']],
            'permata_va' => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'permata']],
            'cimb_va' => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'cimb']],
            'mandiri_bill' => ['payment_type' => 'echannel', 'echannel' => ['bill_info1' => 'Pembayaran', 'bill_info2' => config('app.name')]],
            default => null,
        };
    }
}
