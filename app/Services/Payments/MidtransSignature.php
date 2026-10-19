<?php

namespace App\Services\Payments;

class MidtransSignature
{
    /**
     * signature_key = SHA512(order_id + status_code + gross_amount + server_key).
     * Hanya Midtrans yang tahu server key, jadi notifikasi palsu tidak bisa menghasilkan signature yang cocok.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function isValid(array $payload, string $serverKey): bool
    {
        if ($serverKey === '' || ! isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key'])) {
            return false;
        }

        $expected = self::make($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $serverKey);

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    /** Rumus signature, satu tempat saja: dipakai untuk memeriksa notifikasi dan oleh perintah simulasi lokal. */
    public static function make(string $orderId, string $statusCode, string $grossAmount, string $serverKey): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
    }
}
