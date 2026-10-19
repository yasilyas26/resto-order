<?php

namespace App\Services\Payments;

use Midtrans\Config;
use Midtrans\CoreApi;
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

    public function createSnapToken(string $midtransOrderId, int $grossAmount, string $customerName, int $expiryMinutes, ?array $enabledPayments = null): string
    {
        try {
            return Snap::getSnapToken(array_filter([
                'transaction_details' => [
                    'order_id' => $midtransOrderId,
                    'gross_amount' => $grossAmount,
                ],
                'customer_details' => [
                    'first_name' => mb_substr($customerName, 0, 50),
                ],
                // Ke mana pelanggan dikembalikan setelah bayar di luar popup (e-wallet, 3DS). Tanpa ini
                // Midtrans memakai setelan dashboard, yang bawaannya https://example.com.
                'callbacks' => [
                    'finish' => route('order.finish'),
                ],
                'enabled_payments' => $enabledPayments,
                'expiry' => [
                    'unit' => 'minutes',
                    'duration' => $expiryMinutes,
                ],
            ], fn ($value) => $value !== null));
        } catch (Throwable $e) {
            report($e);
            throw new PaymentGatewayException('Gagal menghubungi Midtrans: '.$e->getMessage(), 0, $e);
        }
    }

    public function charge(string $midtransOrderId, int $grossAmount, string $customerName, array $spec, int $expiryMinutes): array
    {
        try {
            $result = json_decode(json_encode(CoreApi::charge($spec + [
                'transaction_details' => ['order_id' => $midtransOrderId, 'gross_amount' => $grossAmount],
                'customer_details' => ['first_name' => mb_substr($customerName, 0, 50)],
                'custom_expiry' => ['expiry_duration' => $expiryMinutes, 'unit' => 'minute'],
            ])), true);
        } catch (Throwable $e) {
            report($e);
            throw new PaymentGatewayException('Gagal menghubungi Midtrans: '.$e->getMessage(), 0, $e);
        }

        // Transaksi baru yang sah berstatus 201 (menunggu bayar). Selain itu ditolak (mis. metode belum aktif di akun).
        if (! str_starts_with((string) ($result['status_code'] ?? ''), '2')) {
            throw new PaymentGatewayException('Midtrans menolak: '.($result['status_message'] ?? 'tanpa alasan'));
        }

        return $result;
    }

    public function cancel(string $midtransOrderId): void
    {
        try {
            Transaction::cancel($midtransOrderId);
        } catch (Throwable $e) {
            report($e);
            throw new PaymentGatewayException('Gagal membatalkan transaksi di Midtrans: '.$e->getMessage(), 0, $e);
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
