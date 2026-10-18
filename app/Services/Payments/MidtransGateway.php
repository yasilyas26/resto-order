<?php

namespace App\Services\Payments;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;
use Throwable;

class MidtransGateway implements PaymentGateway
{
    public function __construct()
    {
        Config::$serverKey = (string) config('midtrans.server_key');
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function isConfigured(): bool
    {
        return filled(config('midtrans.server_key')) && filled(config('midtrans.client_key'));
    }

    public function createSnapToken(string $midtransOrderId, int $grossAmount, string $customerName, int $expiryMinutes): string
    {
        try {
            return Snap::getSnapToken([
                'transaction_details' => [
                    'order_id' => $midtransOrderId,
                    'gross_amount' => $grossAmount,
                ],
                'customer_details' => [
                    'first_name' => mb_substr($customerName, 0, 50),
                ],
                'expiry' => [
                    'unit' => 'minutes',
                    'duration' => $expiryMinutes,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new PaymentGatewayException('Gagal menghubungi Midtrans: '.$e->getMessage(), 0, $e);
        }
    }

    public function fetchStatus(string $midtransOrderId): ?array
    {
        try {
            $status = Transaction::status($midtransOrderId);
        } catch (Throwable $e) {
            // Midtrans membalas 404 kalau pelanggan belum pernah memilih metode bayar untuk order ini.
            if ($e->getCode() === 404 || str_contains($e->getMessage(), '404')) {
                return null;
            }

            report($e);
            throw new PaymentGatewayException('Gagal menanyakan status ke Midtrans: '.$e->getMessage(), 0, $e);
        }

        return json_decode(json_encode($status), true);
    }
}
